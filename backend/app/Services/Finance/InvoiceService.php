<?php

declare(strict_types=1);

namespace App\Services\Finance;

use App\Models\AuditLog;
use App\Models\Invoice;
use App\Models\SalesOrder;
use App\Models\SalesOrderItem;
use App\Models\User;
use App\Services\MasterData\AuditLogService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class InvoiceService
{
    public function __construct(
        private readonly AuditLogService $auditLogService,
        private readonly InvoiceCalculationService $calculationService,
        private readonly InvoiceWorkflow $workflow,
    ) {}

    /** @param array<string, mixed> $filters */
    public function paginate(array $filters): LengthAwarePaginator
    {
        $query = Invoice::query()->with(['salesOrder', 'buyer', 'customer', 'warehouse', 'creator']);
        $this->applyFilters($query, $filters);
        $sort = in_array(($filters['sort'] ?? 'id'), ['id', 'invoice_number', 'invoice_date', 'due_date', 'total_amount', 'paid_amount', 'due_amount', 'status'], true)
            ? (string) ($filters['sort'] ?? 'id') : 'id';
        $direction = ($filters['direction'] ?? 'desc') === 'asc' ? 'asc' : 'desc';

        return $query->orderBy($sort, $direction)->paginate((int) ($filters['per_page'] ?? 15))->withQueryString();
    }

    public function find(Invoice $invoice): Invoice
    {
        return $invoice->load([
            'salesOrder.buyer',
            'salesOrder.customer',
            'salesOrder.warehouse',
            'buyer',
            'customer',
            'warehouse',
            'creator',
            'items.product',
            'items.productVariant.product',
            'items.unit',
            'payments.receiver',
        ]);
    }

    /** @return Collection<int, SalesOrder> */
    public function eligibleSalesOrders(): Collection
    {
        return SalesOrder::query()
            ->with(['buyer', 'customer', 'warehouse', 'items.product', 'items.productVariant.product', 'items.unit'])
            ->whereIn('status', ['delivered', 'completed'])
            ->where('delivered_quantity', '>', 0)
            ->whereDoesntHave('invoices')
            ->orderByDesc('id')
            ->get();
    }

    /** @param array<string, mixed> $attributes */
    public function create(array $attributes, User $actor): Invoice
    {
        return DB::transaction(function () use ($attributes, $actor): Invoice {
            $salesOrder = SalesOrder::query()
                ->lockForUpdate()
                ->with(['buyer', 'customer', 'warehouse', 'items.product', 'items.productVariant.product', 'items.unit'])
                ->findOrFail((int) $attributes['sales_order_id']);
            $this->assertEligibleSalesOrder($salesOrder);
            if (Invoice::withTrashed()->where('sales_order_id', $salesOrder->getKey())->exists()) {
                throw ValidationException::withMessages(['sales_order_id' => 'This Sales Order already has an invoice.']);
            }

            $sourceItems = $this->resolveInvoiceItems($salesOrder, $attributes['items'] ?? null);
            $calculated = $this->calculationService->calculate($sourceItems);
            $invoice = Invoice::query()->create([
                'invoice_number' => $this->generateInvoiceNumber(),
                'sales_order_id' => $salesOrder->getKey(),
                'buyer_id' => $salesOrder->buyer_id,
                'customer_id' => $salesOrder->customer_id,
                'warehouse_id' => $salesOrder->warehouse_id,
                'invoice_date' => $attributes['invoice_date'],
                'due_date' => $attributes['due_date'],
                'status' => InvoiceWorkflow::DRAFT,
                'subtotal' => $calculated['subtotal'],
                'discount_amount' => $calculated['discount_amount'],
                'tax_amount' => $calculated['tax_amount'],
                'total_amount' => $calculated['total_amount'],
                'paid_amount' => 0,
                'due_amount' => $calculated['total_amount'],
                'issued_at' => null,
                'remarks' => $attributes['remarks'] ?? null,
                'created_by' => $actor->getKey(),
            ]);
            foreach ($calculated['items'] as $item) {
                $created = $invoice->items()->create($item);
                $this->auditLogService->record($actor, 'invoice-items', $created, 'created', null, $created->attributesToArray());
            }
            $this->auditLogService->record($actor, 'invoices', $invoice, 'created', null, $invoice->attributesToArray());

            return $this->find($invoice->refresh());
        });
    }

    /** @param array<string, mixed> $attributes */
    public function update(Invoice $invoice, array $attributes, User $actor): Invoice
    {
        return DB::transaction(function () use ($invoice, $attributes, $actor): Invoice {
            $locked = Invoice::query()->lockForUpdate()->with('items')->findOrFail($invoice->getKey());
            if ($locked->status !== InvoiceWorkflow::DRAFT) {
                throw ValidationException::withMessages(['status' => 'Only draft invoices can be updated.']);
            }
            $oldValues = $locked->attributesToArray();
            $locked->forceFill([
                'invoice_date' => $attributes['invoice_date'] ?? $locked->invoice_date,
                'due_date' => $attributes['due_date'] ?? $locked->due_date,
                'remarks' => array_key_exists('remarks', $attributes) ? $attributes['remarks'] : $locked->remarks,
            ])->save();
            $this->auditLogService->record($actor, 'invoices', $locked, 'updated', $oldValues, $locked->attributesToArray());

            return $this->find($locked->refresh());
        });
    }

    public function issue(Invoice $invoice, ?string $remarks, User $actor): Invoice
    {
        return DB::transaction(function () use ($invoice, $remarks, $actor): Invoice {
            $locked = Invoice::query()->lockForUpdate()->findOrFail($invoice->getKey());
            $this->workflow->assertIssuable($locked);
            $this->applyTransition($locked, InvoiceWorkflow::ISSUED, $remarks ?: 'Invoice issued.', $actor);

            return $this->find($locked->refresh());
        });
    }

    public function cancel(Invoice $invoice, ?string $remarks, User $actor): Invoice
    {
        return DB::transaction(function () use ($invoice, $remarks, $actor): Invoice {
            $locked = Invoice::query()->lockForUpdate()->findOrFail($invoice->getKey());
            if (in_array($locked->status, [InvoiceWorkflow::PAID, InvoiceWorkflow::CANCELLED], true)) {
                throw ValidationException::withMessages(['status' => 'Paid or cancelled invoices cannot be cancelled.']);
            }
            $this->applyTransition($locked, InvoiceWorkflow::CANCELLED, $remarks ?: 'Invoice cancelled.', $actor);

            return $this->find($locked->refresh());
        });
    }

    public function transition(Invoice $invoice, string $newStatus, ?string $remarks, User $actor): Invoice
    {
        if (in_array($newStatus, [InvoiceWorkflow::DRAFT, InvoiceWorkflow::ISSUED, InvoiceWorkflow::PARTIALLY_PAID, InvoiceWorkflow::PAID, InvoiceWorkflow::CANCELLED], true)) {
            throw ValidationException::withMessages(['status' => 'Use the dedicated Invoice or Payment action for this status.']);
        }

        return DB::transaction(function () use ($invoice, $newStatus, $remarks, $actor): Invoice {
            $locked = Invoice::query()->lockForUpdate()->findOrFail($invoice->getKey());
            if ($newStatus === InvoiceWorkflow::OVERDUE && ($locked->due_amount <= 0 || $locked->due_date->isFuture())) {
                throw ValidationException::withMessages(['status' => 'Only invoices past due with an outstanding balance can be marked overdue.']);
            }
            $this->workflow->assertTransition($locked, $newStatus);
            $this->applyTransition($locked, $newStatus, $remarks, $actor);

            return $this->find($locked->refresh());
        });
    }

    /** @return array{status_history: Collection<int, AuditLog>, audit_logs: Collection<int, AuditLog>} */
    public function history(Invoice $invoice): array
    {
        $logs = AuditLog::query()
            ->with('user')
            ->where('module', 'invoices')
            ->where('record_id', $invoice->getKey())
            ->latest('id')
            ->get();

        return ['status_history' => $logs, 'audit_logs' => $logs];
    }

    private function applyFilters(Builder $query, array $filters): void
    {
        if (($filters['search'] ?? null) !== null && $filters['search'] !== '') {
            $search = (string) $filters['search'];
            $query->where(function (Builder $builder) use ($search): void {
                $builder->where('invoice_number', 'like', "%{$search}%")
                    ->orWhere('remarks', 'like', "%{$search}%")
                    ->orWhereHas('salesOrder', fn (Builder $order) => $order->where('sales_order_number', 'like', "%{$search}%"))
                    ->orWhereHas('buyer', fn (Builder $party) => $party->where('code', 'like', "%{$search}%")->orWhere('name', 'like', "%{$search}%"))
                    ->orWhereHas('customer', fn (Builder $party) => $party->where('code', 'like', "%{$search}%")->orWhere('name', 'like', "%{$search}%"));
            });
        }
        foreach (['status', 'buyer_id', 'customer_id', 'sales_order_id', 'warehouse_id'] as $field) {
            if (($filters[$field] ?? null) !== null && $filters[$field] !== '') {
                $query->where($field, $filters[$field]);
            }
        }
        foreach ([
            'invoice_date_from' => ['invoice_date', '>='],
            'invoice_date_to' => ['invoice_date', '<='],
            'due_date_from' => ['due_date', '>='],
            'due_date_to' => ['due_date', '<='],
        ] as $filter => [$column, $operator]) {
            if (($filters[$filter] ?? null) !== null && $filters[$filter] !== '') {
                $query->where($column, $operator, $filters[$filter]);
            }
        }
    }

    private function assertEligibleSalesOrder(SalesOrder $salesOrder): void
    {
        if (! in_array($salesOrder->status, ['delivered', 'completed'], true)) {
            throw ValidationException::withMessages(['sales_order_id' => 'Only delivered or completed Sales Orders can be invoiced.']);
        }
        if ((float) $salesOrder->delivered_quantity <= 0) {
            throw ValidationException::withMessages(['sales_order_id' => 'The Sales Order must have delivered quantity before invoicing.']);
        }
    }

    /** @return array<int, array<string, mixed>> */
    private function resolveInvoiceItems(SalesOrder $salesOrder, mixed $requestedItems): array
    {
        $source = $salesOrder->items->keyBy(fn (SalesOrderItem $item): string => (string) $item->getKey());
        $requested = is_array($requestedItems) && $requestedItems !== [] ? $requestedItems : null;
        $items = [];
        if ($requested === null) {
            foreach ($salesOrder->items as $item) {
                if ((float) $item->delivered_quantity <= 0) {
                    continue;
                }
                $items[] = $this->sourceItem($item, (float) $item->delivered_quantity);
            }
        } else {
            foreach ($requested as $index => $input) {
                $itemId = (int) ($input['sales_order_item_id'] ?? 0);
                $item = $source->get((string) $itemId);
                if ($item === null) {
                    throw ValidationException::withMessages(["items.{$index}.sales_order_item_id" => 'The invoice item must belong to the Sales Order.']);
                }
                $quantity = (float) ($input['quantity'] ?? 0);
                if ($quantity <= 0 || $quantity > (float) $item->delivered_quantity + 0.0000001) {
                    throw ValidationException::withMessages(["items.{$index}.quantity" => 'Invoice quantity must be positive and no greater than delivered quantity.']);
                }
                $items[] = $this->sourceItem($item, $quantity, $input);
            }
        }
        if ($items === []) {
            throw ValidationException::withMessages(['items' => 'The eligible Sales Order has no delivered item quantity to invoice.']);
        }

        return $items;
    }

    /** @param array<string, mixed> $overrides */
    private function sourceItem(SalesOrderItem $item, float $quantity, array $overrides = []): array
    {
        return [
            'sales_order_item_id' => $item->getKey(),
            'line_number' => $item->line_number,
            'product_id' => $item->product_id,
            'product_variant_id' => $item->product_variant_id,
            'unit_id' => $item->unit_id,
            'quantity' => $quantity,
            'unit_price' => $overrides['unit_price'] ?? $item->unit_price,
            'discount_amount' => $overrides['discount_amount'] ?? $item->discount_amount,
            'tax_amount' => $overrides['tax_amount'] ?? $item->tax_amount,
            'remarks' => $overrides['remarks'] ?? $item->remarks,
        ];
    }

    private function applyTransition(Invoice $invoice, string $newStatus, ?string $remarks, User $actor): void
    {
        $oldStatus = $invoice->status;
        $invoice->forceFill([
            'status' => $newStatus,
            'issued_at' => $newStatus === InvoiceWorkflow::ISSUED ? now() : $invoice->issued_at,
        ])->save();
        $this->auditLogService->record($actor, 'invoices', $invoice, 'status_changed', ['status' => $oldStatus], ['status' => $newStatus, 'remarks' => $remarks]);
    }

    private function generateInvoiceNumber(): string
    {
        $prefix = 'INV-'.now()->format('Ymd');
        $sequence = Invoice::withTrashed()->where('invoice_number', 'like', "{$prefix}-%")->count() + 1;
        do {
            $candidate = sprintf('%s-%04d', $prefix, $sequence++);
        } while (Invoice::withTrashed()->where('invoice_number', $candidate)->exists());

        return $candidate;
    }
}
