<?php

declare(strict_types=1);

namespace App\Services\Reporting;

use App\Models\Alert;
use App\Models\AlertRead;
use App\Models\Delivery;
use App\Models\GoodsReceiptItem;
use App\Models\Invoice;
use App\Models\ProductionOrder;
use App\Models\PurchaseOrder;
use App\Models\SupplyPlan;
use App\Models\User;
use App\Services\Delivery\DeliveryWorkflow;
use App\Services\Finance\InvoiceWorkflow;
use App\Services\Procurement\ProcurementWorkflow;
use App\Services\Production\ProductionWorkflow;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

final class AlertService
{
    private const RULE_PERMISSIONS = [
        'invoice_overdue' => 'finance.view',
        'invoice_partially_paid' => 'finance.view',
        'purchase_order_delayed' => 'procurement.view',
        'delivery_delayed' => 'delivery.view',
        'production_delayed' => 'production.view',
        'demand_without_available_stock' => 'planning.view',
        'supplier_receipt_rejection' => 'procurement.view',
    ];

    public function index(User $user, array $filters): LengthAwarePaginator
    {
        $this->refresh($user);
        $permissionSlugs = $this->permissionSlugs($user);
        $roleSlugs = $user->roles()->pluck('slug')->all();
        $query = Alert::query()
            ->leftJoin('alert_reads', function ($join) use ($user): void {
                $join->on('alerts.id', '=', 'alert_reads.alert_id')->where('alert_reads.user_id', $user->getKey());
            })
            ->select('alerts.*', 'alert_reads.read_at as user_read_at')
            ->where(function (Builder $builder) use ($permissionSlugs): void {
                $builder->whereNull('alerts.permission_slug')->orWhereIn('alerts.permission_slug', $permissionSlugs);
            })
            ->where(function (Builder $builder) use ($roleSlugs): void {
                $builder->whereNull('alerts.role_slug')->orWhereIn('alerts.role_slug', $roleSlugs);
            });
        if (! ($filters['include_resolved'] ?? false)) {
            $query->whereNull('alerts.resolved_at');
        }
        if (($filters['severity'] ?? null) !== null) {
            $query->where('alerts.severity', $filters['severity']);
        }
        if (($filters['rule_code'] ?? null) !== null) {
            $query->where('alerts.rule_code', $filters['rule_code']);
        }
        if (($filters['read'] ?? null) !== null) {
            $filters['read'] ? $query->whereNotNull('alert_reads.read_at') : $query->whereNull('alert_reads.read_at');
        }
        if (($filters['search'] ?? null) !== null) {
            $search = '%'.$filters['search'].'%';
            $query->where(fn (Builder $builder) => $builder->where('alerts.title', 'like', $search)->orWhere('alerts.description', 'like', $search)->orWhere('alerts.rule_code', 'like', $search));
        }
        $perPage = min(100, max(1, (int) ($filters['per_page'] ?? 15)));

        return $query->orderByRaw('CASE alerts.severity WHEN \'critical\' THEN 1 WHEN \'warning\' THEN 2 ELSE 3 END')->orderByDesc('alerts.occurred_at')->paginate($perPage)->withQueryString();
    }

    /** @return array<int, Alert> */
    public function refresh(User $user): array
    {
        $today = now()->toDateString();
        $candidates = [];
        $permissions = $this->permissionSlugs($user);
        $add = static function (array &$target, string $rule, string $severity, string $title, string $description, string $type, int $id, string $occurredAt) use ($permissions): void {
            $permission = self::RULE_PERMISSIONS[$rule];
            if (! in_array($permission, $permissions, true)) {
                return;
            }
            $target[] = compact('rule', 'severity', 'title', 'description', 'type', 'id', 'occurredAt', 'permission');
        };

        if (in_array('finance.view', $permissions, true)) {
            Invoice::query()->whereNotIn('status', [InvoiceWorkflow::DRAFT, InvoiceWorkflow::CANCELLED])->where('due_amount', '>', 0)->whereDate('due_date', '<', $today)->get()->each(function (Invoice $invoice) use (&$candidates, $add): void {
                $add($candidates, 'invoice_overdue', 'critical', 'Overdue invoice: '.$invoice->invoice_number, 'Invoice '.$invoice->invoice_number.' is past due with an outstanding balance of '.$invoice->due_amount.'. Rule: due date is before today and due amount is positive.', Invoice::class, $invoice->id, (string) $invoice->due_date);
            });
            Invoice::query()->whereNotIn('status', [InvoiceWorkflow::DRAFT, InvoiceWorkflow::CANCELLED])->where('paid_amount', '>', 0)->whereColumn('paid_amount', '<', 'total_amount')->get()->each(function (Invoice $invoice) use (&$candidates, $add): void {
                $add($candidates, 'invoice_partially_paid', 'warning', 'Partially paid invoice: '.$invoice->invoice_number, 'Invoice '.$invoice->invoice_number.' has received '.$invoice->paid_amount.' against '.$invoice->total_amount.'. Rule: paid amount is positive and below total.', Invoice::class, $invoice->id, (string) $invoice->invoice_date);
            });
        }

        if (in_array('procurement.view', $permissions, true)) {
            PurchaseOrder::query()->whereNotIn('status', [ProcurementWorkflow::DRAFT, ProcurementWorkflow::CANCELLED, ProcurementWorkflow::CLOSED, ProcurementWorkflow::RECEIVED])->whereDate('expected_delivery_date', '<', $today)->with('items')->get()->each(function (PurchaseOrder $order) use (&$candidates, $add): void {
                $ordered = (float) $order->items->sum('quantity');
                $received = (float) $order->items->sum('received_quantity');
                if ($received >= $ordered && $ordered > 0) {
                    return;
                }
                $short = $ordered > 0 ? round(max(0, $ordered - $received), 4) : 0;
                $add($candidates, 'purchase_order_delayed', 'warning', 'Delayed Purchase Order: '.$order->purchase_order_number, 'Purchase Order '.$order->purchase_order_number.' was expected by '.$order->expected_delivery_date->format('Y-m-d').' and remains '.$short.' unit(s) short of ordered quantity. Rule: non-terminal PO is past expected date with incomplete receipt.', PurchaseOrder::class, $order->id, (string) $order->expected_delivery_date);
            });
            GoodsReceiptItem::query()->where('rejected_quantity', '>', 0)->with('goodsReceipt')->get()->each(function (GoodsReceiptItem $item) use (&$candidates, $add): void {
                $receipt = $item->goodsReceipt;
                if ($receipt === null) {
                    return;
                }
                $add($candidates, 'supplier_receipt_rejection', 'warning', 'Rejected receipt quantity: '.$receipt->receipt_number, 'Goods Receipt '.$receipt->receipt_number.' records '.$item->rejected_quantity.' rejected unit(s). Rule: rejected quantity is positive.', GoodsReceiptItem::class, $item->id, (string) $receipt->receipt_date);
            });
        }

        if (in_array('delivery.view', $permissions, true)) {
            Delivery::query()->whereNotIn('status', [DeliveryWorkflow::DELIVERED, DeliveryWorkflow::COMPLETED, DeliveryWorkflow::CANCELLED, DeliveryWorkflow::RETURNED])->whereNotNull('expected_delivery_date')->whereDate('expected_delivery_date', '<', $today)->get()->each(function (Delivery $delivery) use (&$candidates, $add): void {
                $add($candidates, 'delivery_delayed', 'warning', 'Delayed delivery: '.$delivery->delivery_number, 'Delivery '.$delivery->delivery_number.' passed its expected date of '.$delivery->expected_delivery_date->format('Y-m-d').' while status is '.$delivery->status.'. Rule: active delivery is past expected date.', Delivery::class, $delivery->id, (string) $delivery->expected_delivery_date);
            });
        }

        if (in_array('production.view', $permissions, true)) {
            ProductionOrder::query()->whereNotIn('status', [ProductionWorkflow::COMPLETED, ProductionWorkflow::CANCELLED])->whereDate('expected_completion_date', '<', $today)->whereColumn('completed_quantity', '<', 'planned_quantity')->get()->each(function (ProductionOrder $order) use (&$candidates, $add): void {
                $add($candidates, 'production_delayed', 'warning', 'Delayed production order: '.$order->order_number, 'Production order '.$order->order_number.' passed expected completion on '.$order->expected_completion_date->format('Y-m-d').' with remaining planned quantity. Rule: non-terminal order is past date and completed quantity is below planned quantity.', ProductionOrder::class, $order->id, (string) $order->expected_completion_date);
            });
        }

        if (in_array('planning.view', $permissions, true)) {
            SupplyPlan::query()->whereIn('status', ['active', 'approved', 'generated'])->whereColumn('required_quantity', '>', 'available_quantity')->get()->each(function (SupplyPlan $plan) use (&$candidates, $add): void {
                $short = max(0, (float) $plan->required_quantity - (float) $plan->available_quantity);
                $add($candidates, 'demand_without_available_stock', 'critical', 'Demand exceeds available supply', 'Supply plan '.$plan->id.' has a real requirement shortfall of '.round($short, 4).' unit(s). Rule: required quantity is greater than available quantity.', SupplyPlan::class, $plan->id, (string) $plan->period_start);
            });
        }

        return DB::transaction(function () use ($candidates, $permissions): array {
            $activeKeys = [];
            $alerts = [];
            foreach ($candidates as $candidate) {
                $key = $candidate['rule'].':'.$candidate['type'].':'.$candidate['id'];
                $activeKeys[] = $key;
                $alert = Alert::query()->updateOrCreate(['alert_key' => $key], [
                    'rule_code' => $candidate['rule'],
                    'severity' => $candidate['severity'],
                    'title' => $candidate['title'],
                    'description' => $candidate['description'],
                    'related_type' => $candidate['type'],
                    'related_id' => $candidate['id'],
                    'permission_slug' => $candidate['permission'],
                    'occurred_at' => $candidate['occurredAt'],
                    'resolved_at' => null,
                ]);
                $alerts[] = $alert;
            }
            Alert::query()->whereNull('resolved_at')->whereIn('permission_slug', $permissions)->when($activeKeys !== [], fn ($query) => $query->whereNotIn('alert_key', $activeKeys))->update(['resolved_at' => now()]);

            return $alerts;
        });
    }

    public function markRead(User $user, Alert $alert): AlertRead
    {
        abort_unless($this->isRelevant($user, $alert), 403);

        return AlertRead::query()->updateOrCreate(['alert_id' => $alert->id, 'user_id' => $user->id], ['read_at' => now()]);
    }

    public function markUnread(User $user, Alert $alert): void
    {
        abort_unless($this->isRelevant($user, $alert), 403);
        AlertRead::query()->where('alert_id', $alert->id)->where('user_id', $user->id)->delete();
    }

    private function isRelevant(User $user, Alert $alert): bool
    {
        return ($alert->permission_slug === null || $user->hasPermission($alert->permission_slug)) && ($alert->role_slug === null || $user->roles()->where('slug', $alert->role_slug)->exists());
    }

    /** @return array<int, string> */
    private function permissionSlugs(User $user): array
    {
        return $user->roles()->with('permissions')->get()->flatMap(fn ($role) => $role->permissions->pluck('slug'))->unique()->values()->all();
    }
}
