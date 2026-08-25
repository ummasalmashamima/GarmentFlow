<?php

declare(strict_types=1);

namespace App\Services\Orders;

use App\Models\AuditLog;
use App\Models\Buyer;
use App\Models\BuyerOrder;
use App\Models\BuyerOrderItem;
use App\Models\OrderStatusHistory;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\User;
use App\Services\MasterData\AuditLogService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class BuyerOrderService
{
    public function __construct(
        private readonly AuditLogService $auditLogService,
        private readonly BuyerOrderWorkflow $workflow,
        private readonly BuyerOrderCalculationService $calculationService,
    ) {}

    /**
     * @param  array<string, mixed>  $filters
     */
    public function paginate(array $filters): LengthAwarePaginator
    {
        $query = BuyerOrder::query()->with(['buyer', 'creator']);

        if ($filters['search'] ?? null) {
            $search = (string) $filters['search'];
            $query->where(function (Builder $builder) use ($search): void {
                $builder->where('order_number', 'like', "%{$search}%")
                    ->orWhere('remarks', 'like', "%{$search}%")
                    ->orWhereHas('buyer', function (Builder $buyerQuery) use ($search): void {
                        $buyerQuery->where('code', 'like', "%{$search}%")
                            ->orWhere('name', 'like', "%{$search}%");
                    });
            });
        }

        if (($filters['status'] ?? null) !== null && $filters['status'] !== '') {
            $query->where('status', $filters['status']);
        }

        if (($filters['buyer_id'] ?? null) !== null && $filters['buyer_id'] !== '') {
            $query->where('buyer_id', (int) $filters['buyer_id']);
        }

        foreach ([
            'order_date_from' => ['order_date', '>='],
            'order_date_to' => ['order_date', '<='],
            'delivery_date_from' => ['delivery_date', '>='],
            'delivery_date_to' => ['delivery_date', '<='],
        ] as $filter => [$column, $operator]) {
            if (($filters[$filter] ?? null) !== null && $filters[$filter] !== '') {
                $query->where($column, $operator, $filters[$filter]);
            }
        }

        return $query
            ->orderBy((string) ($filters['sort'] ?? 'id'), (string) ($filters['direction'] ?? 'desc'))
            ->paginate((int) ($filters['per_page'] ?? 15))
            ->withQueryString();
    }

    public function find(BuyerOrder $order): BuyerOrder
    {
        return $order->load([
            'buyer',
            'creator',
            'items.product',
            'items.productVariant.product',
            'approvals.requester',
            'approvals.reviewer',
            'latestApproval.requester',
            'latestApproval.reviewer',
            'statusHistories.changer',
            'planningInput.preparer',
        ]);
    }

    /**
     * @param  array<int, array<string, mixed>>  $items
     * @return array{total_quantity: string, total_amount: string, items: array<int, array<string, mixed>>}
     */
    public function previewTotals(array $items): array
    {
        $this->validateItemReferences($items);

        return $this->calculationService->calculate($items);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function create(array $attributes, User $actor): BuyerOrder
    {
        return DB::transaction(function () use ($attributes, $actor): BuyerOrder {
            $this->ensureActiveBuyer((int) $attributes['buyer_id']);
            $this->validateItemReferences($attributes['items']);
            $order = BuyerOrder::query()->create([
                'buyer_id' => $attributes['buyer_id'],
                'order_number' => $this->generateOrderNumber(),
                'order_date' => $attributes['order_date'],
                'delivery_date' => $attributes['delivery_date'],
                'status' => BuyerOrderWorkflow::DRAFT,
                'total_quantity' => 0,
                'total_amount' => 0,
                'remarks' => $attributes['remarks'] ?? null,
                'created_by' => $actor->getKey(),
            ]);

            $this->replaceItems($order, $attributes['items'], $actor);
            $this->refreshTotals($order);
            $this->recordStatus($order, null, BuyerOrderWorkflow::DRAFT, $actor, 'Draft order created.');
            $this->auditLogService->record($actor, 'buyer-orders', $order, 'created', null, $order->attributesToArray());

            return $this->find($order->refresh());
        });
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function update(BuyerOrder $order, array $attributes, User $actor): BuyerOrder
    {
        return DB::transaction(function () use ($order, $attributes, $actor): BuyerOrder {
            $this->workflow->assertDraft($order);
            $this->ensureActiveBuyer((int) $attributes['buyer_id']);
            $this->validateItemReferences($attributes['items']);
            $oldValues = $order->attributesToArray();
            $order->fill([
                'buyer_id' => $attributes['buyer_id'],
                'order_date' => $attributes['order_date'],
                'delivery_date' => $attributes['delivery_date'],
                'remarks' => $attributes['remarks'] ?? null,
            ]);
            $order->save();
            $this->replaceItems($order, $attributes['items'], $actor);
            $this->refreshTotals($order);
            $this->auditLogService->record($actor, 'buyer-orders', $order, 'updated', $oldValues, $order->attributesToArray());

            return $this->find($order->refresh());
        });
    }

    public function delete(BuyerOrder $order, User $actor): BuyerOrder
    {
        return DB::transaction(function () use ($order, $actor): BuyerOrder {
            $this->workflow->assertDraft($order);
            $oldValues = $order->attributesToArray();
            $order->delete();
            $this->auditLogService->record($actor, 'buyer-orders', $order, 'deleted', $oldValues, $order->attributesToArray());

            return $order;
        });
    }

    public function submit(BuyerOrder $order, ?string $remarks, User $actor): BuyerOrder
    {
        return DB::transaction(function () use ($order, $remarks, $actor): BuyerOrder {
            $this->workflow->assertDraft($order);
            $this->ensureOrderIntegrity($order);
            $this->applyTransition($order, BuyerOrderWorkflow::SUBMITTED, $remarks ?: 'Order submitted for approval.', $actor);

            $approval = $order->approvals()->create([
                'requested_by' => $actor->getKey(),
                'status' => 'pending',
                'remarks' => $remarks,
                'requested_at' => now(),
            ]);
            $this->auditLogService->record($actor, 'order-approvals', $approval, 'requested', null, $approval->attributesToArray());
            $this->applyTransition($order, BuyerOrderWorkflow::PENDING_APPROVAL, $remarks ?: 'Order is awaiting approval.', $actor);
            $this->auditLogService->record($actor, 'buyer-orders', $order, 'submitted', null, ['status' => $order->status]);

            return $this->find($order->refresh());
        });
    }

    public function approve(BuyerOrder $order, ?string $remarks, User $actor): BuyerOrder
    {
        return DB::transaction(function () use ($order, $remarks, $actor): BuyerOrder {
            if ($order->status !== BuyerOrderWorkflow::PENDING_APPROVAL) {
                throw ValidationException::withMessages(['status' => 'Only orders pending approval can be approved.']);
            }

            $approval = $order->approvals()->where('status', 'pending')->latest('id')->first();
            if ($approval === null) {
                throw ValidationException::withMessages(['approval' => 'A pending approval request is required.']);
            }

            $oldApproval = $approval->attributesToArray();
            $approval->forceFill([
                'status' => 'approved',
                'reviewed_by' => $actor->getKey(),
                'reviewed_at' => now(),
                'remarks' => $remarks ?? $approval->remarks,
            ])->save();
            $this->auditLogService->record($actor, 'order-approvals', $approval, 'approved', $oldApproval, $approval->attributesToArray());
            $this->applyTransition($order, BuyerOrderWorkflow::SUBMITTED, $remarks ?: 'Order approved and ready for confirmation.', $actor);
            $this->auditLogService->record($actor, 'buyer-orders', $order, 'approved', null, ['status' => $order->status]);

            return $this->find($order->refresh());
        });
    }

    public function reject(BuyerOrder $order, ?string $remarks, User $actor): BuyerOrder
    {
        return DB::transaction(function () use ($order, $remarks, $actor): BuyerOrder {
            if ($order->status !== BuyerOrderWorkflow::PENDING_APPROVAL) {
                throw ValidationException::withMessages(['status' => 'Only orders pending approval can be rejected.']);
            }

            $approval = $order->approvals()->where('status', 'pending')->latest('id')->first();
            if ($approval === null) {
                throw ValidationException::withMessages(['approval' => 'A pending approval request is required.']);
            }

            $oldApproval = $approval->attributesToArray();
            $approval->forceFill([
                'status' => 'rejected',
                'reviewed_by' => $actor->getKey(),
                'reviewed_at' => now(),
                'remarks' => $remarks ?? $approval->remarks,
            ])->save();
            $this->auditLogService->record($actor, 'order-approvals', $approval, 'rejected', $oldApproval, $approval->attributesToArray());
            $this->applyTransition($order, BuyerOrderWorkflow::DRAFT, $remarks ?: 'Order rejected and returned to draft.', $actor);
            $this->auditLogService->record($actor, 'buyer-orders', $order, 'rejected', null, ['status' => $order->status]);

            return $this->find($order->refresh());
        });
    }

    public function confirm(BuyerOrder $order, ?string $remarks, User $actor): BuyerOrder
    {
        return DB::transaction(function () use ($order, $remarks, $actor): BuyerOrder {
            if ($order->status !== BuyerOrderWorkflow::SUBMITTED) {
                throw ValidationException::withMessages(['status' => 'Only approved submitted orders can be confirmed.']);
            }

            $approval = $order->approvals()->where('status', 'approved')->latest('id')->first();
            if ($approval === null) {
                throw ValidationException::withMessages(['approval' => 'An approved order is required before confirmation.']);
            }

            $this->ensureOrderIntegrity($order);
            $planningInput = $order->planningInput()->first();
            if ($planningInput !== null) {
                throw ValidationException::withMessages(['status' => 'This order has already been prepared for planning.']);
            }

            $this->applyTransition($order, BuyerOrderWorkflow::CONFIRMED, $remarks ?: 'Order confirmed for future planning.', $actor);
            $planningInput = $order->planningInput()->create([
                'status' => 'ready',
                'total_quantity' => $order->total_quantity,
                'prepared_by' => $actor->getKey(),
                'prepared_at' => now(),
                'notes' => $remarks,
            ]);
            $this->auditLogService->record($actor, 'order-planning-inputs', $planningInput, 'created', null, $planningInput->attributesToArray());
            $this->auditLogService->record($actor, 'buyer-orders', $order, 'confirmed', null, ['status' => $order->status]);

            return $this->find($order->refresh());
        });
    }

    public function transition(BuyerOrder $order, string $newStatus, ?string $remarks, User $actor): BuyerOrder
    {
        if ($newStatus === BuyerOrderWorkflow::DRAFT || $newStatus === BuyerOrderWorkflow::SUBMITTED || $newStatus === BuyerOrderWorkflow::PENDING_APPROVAL || $newStatus === BuyerOrderWorkflow::CONFIRMED) {
            throw ValidationException::withMessages(['status' => 'Use the dedicated submit, approval, or confirmation action for this status.']);
        }

        $this->workflow->assertTransition($order, $newStatus);
        $this->ensureOrderIntegrity($order);

        $this->applyTransition($order, $newStatus, $remarks, $actor);

        return $this->find($order->refresh());
    }

    private function applyTransition(BuyerOrder $order, string $newStatus, ?string $remarks, User $actor): BuyerOrder
    {
        $oldStatus = $order->status;
        $order->forceFill(['status' => $newStatus])->save();
        $this->recordStatus($order, $oldStatus, $newStatus, $actor, $remarks);
        $this->auditLogService->record($actor, 'buyer-orders', $order, 'status_changed', ['status' => $oldStatus], ['status' => $newStatus, 'remarks' => $remarks]);

        return $order;
    }

    /**
     * @return Collection<int, BuyerOrderItem>
     */
    public function items(BuyerOrder $order): Collection
    {
        return $order->items()->with(['product', 'productVariant.product'])->get();
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function addItem(BuyerOrder $order, array $attributes, User $actor): BuyerOrderItem
    {
        return DB::transaction(function () use ($order, $attributes, $actor): BuyerOrderItem {
            $this->workflow->assertDraft($order);
            $this->validateItemReferences([$attributes]);
            if ($order->items()->where('product_variant_id', $attributes['product_variant_id'])->exists()) {
                throw ValidationException::withMessages(['product_variant_id' => 'This product variant already exists on the order.']);
            }
            $item = $this->createItem($order, $attributes);
            $this->refreshTotals($order);
            $this->auditLogService->record($actor, 'buyer-order-items', $item, 'created', null, $item->attributesToArray());

            return $item->load(['product', 'productVariant.product']);
        });
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function updateItem(BuyerOrder $order, BuyerOrderItem $item, array $attributes, User $actor): BuyerOrderItem
    {
        return DB::transaction(function () use ($order, $item, $attributes, $actor): BuyerOrderItem {
            $this->workflow->assertDraft($order);
            $this->ensureItemBelongsToOrder($order, $item);
            $this->validateItemReferences([$attributes]);
            if ($order->items()->where('product_variant_id', $attributes['product_variant_id'])->whereKeyNot($item->getKey())->exists()) {
                throw ValidationException::withMessages(['product_variant_id' => 'This product variant already exists on the order.']);
            }
            $oldValues = $item->attributesToArray();
            $item->fill([
                'product_id' => $attributes['product_id'],
                'product_variant_id' => $attributes['product_variant_id'],
                'quantity' => $attributes['quantity'],
                'unit_price' => $attributes['unit_price'],
                'item_total' => $this->calculationService->lineTotal($attributes),
                'remarks' => $attributes['remarks'] ?? null,
            ])->save();
            $this->refreshTotals($order);
            $this->auditLogService->record($actor, 'buyer-order-items', $item, 'updated', $oldValues, $item->attributesToArray());

            return $item->load(['product', 'productVariant.product']);
        });
    }

    public function deleteItem(BuyerOrder $order, BuyerOrderItem $item, User $actor): BuyerOrderItem
    {
        return DB::transaction(function () use ($order, $item, $actor): BuyerOrderItem {
            $this->workflow->assertDraft($order);
            $this->ensureItemBelongsToOrder($order, $item);
            if ($order->items()->count() <= 1) {
                throw ValidationException::withMessages(['items' => 'An order must contain at least one item.']);
            }
            $oldValues = $item->attributesToArray();
            $item->delete();
            $this->refreshTotals($order);
            $this->auditLogService->record($actor, 'buyer-order-items', $item, 'deleted', $oldValues, null);

            return $item;
        });
    }

    /**
     * @return array{status_history: Collection<int, OrderStatusHistory>, audit_logs: Collection<int, AuditLog>}
     */
    public function history(BuyerOrder $order): array
    {
        return [
            'status_history' => $order->statusHistories()->with('changer')->get(),
            'audit_logs' => AuditLog::query()
                ->where('module', 'buyer-orders')
                ->where('record_id', $order->getKey())
                ->latest('id')
                ->get(),
        ];
    }

    /**
     * @param  array<int, array<string, mixed>>  $items
     */
    private function replaceItems(BuyerOrder $order, array $items, User $actor): void
    {
        $order->items()->delete();
        foreach ($items as $item) {
            $created = $this->createItem($order, $item);
            $this->auditLogService->record($actor, 'buyer-order-items', $created, 'created', null, $created->attributesToArray());
        }
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function createItem(BuyerOrder $order, array $attributes): BuyerOrderItem
    {
        return $order->items()->create([
            'product_id' => $attributes['product_id'],
            'product_variant_id' => $attributes['product_variant_id'],
            'quantity' => $attributes['quantity'],
            'unit_price' => $attributes['unit_price'],
            'item_total' => $this->calculationService->lineTotal($attributes),
            'remarks' => $attributes['remarks'] ?? null,
        ]);
    }

    private function refreshTotals(BuyerOrder $order): void
    {
        $items = $order->items()->get()->map(fn (BuyerOrderItem $item): array => [
            'product_id' => $item->product_id,
            'product_variant_id' => $item->product_variant_id,
            'quantity' => $item->quantity,
            'unit_price' => $item->unit_price,
            'remarks' => $item->remarks,
        ])->all();
        $totals = $this->calculationService->calculate($items);
        $order->forceFill([
            'total_quantity' => $totals['total_quantity'],
            'total_amount' => $totals['total_amount'],
        ])->save();
    }

    /**
     * @param  array<int, array<string, mixed>>  $items
     */
    private function validateItemReferences(array $items): void
    {
        $variantIds = [];
        foreach ($items as $index => $item) {
            $product = Product::query()->whereKey($item['product_id'] ?? null)->where('status', 'active')->first();
            if ($product === null) {
                throw ValidationException::withMessages(["items.{$index}.product_id" => 'The selected product must exist and be active.']);
            }
            $variant = ProductVariant::query()->whereKey($item['product_variant_id'] ?? null)->where('status', 'active')->first();
            if ($variant === null) {
                throw ValidationException::withMessages(["items.{$index}.product_variant_id" => 'The selected product variant must exist and be active.']);
            }
            if ((int) $variant->product_id !== (int) $product->getKey()) {
                throw ValidationException::withMessages(["items.{$index}.product_variant_id" => 'The selected variant does not belong to the selected product.']);
            }
            $variantIds[] = (int) $variant->getKey();
        }

        if (count($variantIds) !== count(array_unique($variantIds))) {
            throw ValidationException::withMessages(['items' => 'Each product variant may appear only once on an order.']);
        }
    }

    private function ensureOrderIntegrity(BuyerOrder $order): void
    {
        $order->loadMissing('buyer', 'items.product', 'items.productVariant');
        if ($order->buyer === null || $order->buyer->status !== 'active' || $order->buyer->deleted_at !== null) {
            throw ValidationException::withMessages(['buyer_id' => 'The buyer must exist and be active.']);
        }
        if ($order->items->isEmpty()) {
            throw ValidationException::withMessages(['items' => 'An order must contain at least one item.']);
        }
        if ($order->delivery_date->lt($order->order_date)) {
            throw ValidationException::withMessages(['delivery_date' => 'The delivery date must be on or after the order date.']);
        }
        $this->validateItemReferences($order->items->map(fn (BuyerOrderItem $item): array => [
            'product_id' => $item->product_id,
            'product_variant_id' => $item->product_variant_id,
            'quantity' => $item->quantity,
            'unit_price' => $item->unit_price,
            'remarks' => $item->remarks,
        ])->all());
        $this->refreshTotals($order);
    }

    private function ensureActiveBuyer(int $buyerId): void
    {
        if (! Buyer::query()->whereKey($buyerId)->where('status', 'active')->exists()) {
            throw ValidationException::withMessages(['buyer_id' => 'The selected buyer must exist and be active.']);
        }
    }

    private function ensureItemBelongsToOrder(BuyerOrder $order, BuyerOrderItem $item): void
    {
        if ((int) $item->buyer_order_id !== (int) $order->getKey()) {
            abort(404, 'The order item does not belong to this order.');
        }
    }

    private function recordStatus(BuyerOrder $order, ?string $previousStatus, string $newStatus, User $actor, ?string $remarks): void
    {
        $order->statusHistories()->create([
            'previous_status' => $previousStatus,
            'new_status' => $newStatus,
            'changed_by' => $actor->getKey(),
            'remarks' => $remarks,
        ]);
    }

    private function generateOrderNumber(): string
    {
        $prefix = 'BO-'.now()->format('Ymd');
        $sequence = BuyerOrder::withTrashed()->where('order_number', 'like', "{$prefix}-%")->count() + 1;
        $candidate = sprintf('%s-%04d', $prefix, $sequence);

        while (BuyerOrder::withTrashed()->where('order_number', $candidate)->exists()) {
            $sequence++;
            $candidate = sprintf('%s-%04d', $prefix, $sequence);
        }

        return $candidate;
    }
}
