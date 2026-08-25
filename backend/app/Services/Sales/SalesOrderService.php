<?php

declare(strict_types=1);

namespace App\Services\Sales;

use App\Models\AuditLog;
use App\Models\Buyer;
use App\Models\Customer;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\SalesOrder;
use App\Models\SalesOrderItem;
use App\Models\SalesOrderStatusHistory;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\Inventory\InventoryService;
use App\Services\MasterData\AuditLogService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class SalesOrderService
{
    public function __construct(
        private readonly AuditLogService $auditLogService,
        private readonly SalesOrderWorkflow $workflow,
        private readonly SalesOrderCalculationService $calculationService,
        private readonly InventoryService $inventoryService,
    ) {}

    /** @param array<string, mixed> $filters */
    public function paginate(array $filters): LengthAwarePaginator
    {
        $query = SalesOrder::query()->with(['buyer', 'customer', 'warehouse', 'creator']);

        if (($filters['search'] ?? null) !== null && $filters['search'] !== '') {
            $search = (string) $filters['search'];
            $query->where(function (Builder $builder) use ($search): void {
                $builder->where('sales_order_number', 'like', "%{$search}%")
                    ->orWhere('remarks', 'like', "%{$search}%")
                    ->orWhereHas('buyer', fn (Builder $party) => $party->where('code', 'like', "%{$search}%")->orWhere('name', 'like', "%{$search}%"))
                    ->orWhereHas('customer', fn (Builder $party) => $party->where('code', 'like', "%{$search}%")->orWhere('name', 'like', "%{$search}%"));
            });
        }

        foreach (['status', 'buyer_id', 'customer_id', 'warehouse_id'] as $field) {
            if (($filters[$field] ?? null) !== null && $filters[$field] !== '') {
                $query->where($field, $filters[$field]);
            }
        }

        foreach ([
            'order_date_from' => ['order_date', '>='],
            'order_date_to' => ['order_date', '<='],
            'required_delivery_date_from' => ['required_delivery_date', '>='],
            'required_delivery_date_to' => ['required_delivery_date', '<='],
        ] as $filter => [$column, $operator]) {
            if (($filters[$filter] ?? null) !== null && $filters[$filter] !== '') {
                $query->where($column, $operator, $filters[$filter]);
            }
        }

        $sort = in_array(($filters['sort'] ?? 'id'), ['id', 'sales_order_number', 'order_date', 'required_delivery_date', 'ordered_quantity', 'total_amount', 'status'], true)
            ? (string) ($filters['sort'] ?? 'id') : 'id';
        $direction = ($filters['direction'] ?? 'desc') === 'asc' ? 'asc' : 'desc';

        return $query->orderBy($sort, $direction)->paginate((int) ($filters['per_page'] ?? 15))->withQueryString();
    }

    public function find(SalesOrder $order): SalesOrder
    {
        return $order->load([
            'buyer',
            'customer',
            'warehouse',
            'creator',
            'items.product',
            'items.productVariant.product',
            'items.unit',
            'statusHistories.changer',
        ]);
    }

    /** @param array<int, array<string, mixed>> $items */
    public function previewTotals(array $items, float|int|string $orderDiscount = 0, float|int|string $orderTax = 0): array
    {
        $this->validateItemReferences($items);

        return $this->calculationService->calculate($items, $orderDiscount, $orderTax);
    }

    /** @param array<string, mixed> $attributes */
    public function create(array $attributes, User $actor): SalesOrder
    {
        return DB::transaction(function () use ($attributes, $actor): SalesOrder {
            $this->ensureActiveParty($attributes);
            $this->ensureActiveWarehouse((int) $attributes['warehouse_id']);
            $this->validateItemReferences($attributes['items']);
            $totals = $this->calculationService->calculate(
                $attributes['items'],
                $attributes['order_discount_amount'] ?? 0,
                $attributes['order_tax_amount'] ?? 0,
            );
            $order = SalesOrder::query()->create([
                'sales_order_number' => $this->generateOrderNumber(),
                'buyer_id' => $attributes['buyer_id'] ?? null,
                'customer_id' => $attributes['customer_id'] ?? null,
                'order_date' => $attributes['order_date'],
                'required_delivery_date' => $attributes['required_delivery_date'],
                'warehouse_id' => $attributes['warehouse_id'],
                'delivery_address' => $attributes['delivery_address'] ?? null,
                'contact_information' => $attributes['contact_information'] ?? null,
                'status' => SalesOrderWorkflow::DRAFT,
                'subtotal' => $totals['subtotal'],
                'order_discount_amount' => $attributes['order_discount_amount'] ?? 0,
                'order_tax_amount' => $attributes['order_tax_amount'] ?? 0,
                'discount_amount' => $totals['discount_amount'],
                'tax_amount' => $totals['tax_amount'],
                'total_amount' => $totals['total_amount'],
                'ordered_quantity' => $totals['total_quantity'],
                'confirmed_quantity' => 0,
                'delivered_quantity' => 0,
                'remaining_quantity' => $totals['total_quantity'],
                'remarks' => $attributes['remarks'] ?? null,
                'created_by' => $actor->getKey(),
            ]);
            $this->replaceItems($order, $attributes['items'], $totals['items'], $actor);
            $this->recordStatus($order, null, SalesOrderWorkflow::DRAFT, $actor, 'Sales Order draft created.');
            $this->auditLogService->record($actor, 'sales-orders', $order, 'created', null, $order->attributesToArray());

            return $this->find($order->refresh());
        });
    }

    /** @param array<string, mixed> $attributes */
    public function update(SalesOrder $order, array $attributes, User $actor): SalesOrder
    {
        return DB::transaction(function () use ($order, $attributes, $actor): SalesOrder {
            $this->workflow->assertDraft($order);
            $this->ensureActiveParty($attributes);
            $this->ensureActiveWarehouse((int) $attributes['warehouse_id']);
            $this->validateItemReferences($attributes['items']);
            $totals = $this->calculationService->calculate(
                $attributes['items'],
                $attributes['order_discount_amount'] ?? 0,
                $attributes['order_tax_amount'] ?? 0,
            );
            $oldValues = $order->attributesToArray();
            $order->fill([
                'buyer_id' => $attributes['buyer_id'] ?? null,
                'customer_id' => $attributes['customer_id'] ?? null,
                'order_date' => $attributes['order_date'],
                'required_delivery_date' => $attributes['required_delivery_date'],
                'warehouse_id' => $attributes['warehouse_id'],
                'delivery_address' => $attributes['delivery_address'] ?? null,
                'contact_information' => $attributes['contact_information'] ?? null,
                'order_discount_amount' => $attributes['order_discount_amount'] ?? 0,
                'order_tax_amount' => $attributes['order_tax_amount'] ?? 0,
                'subtotal' => $totals['subtotal'],
                'discount_amount' => $totals['discount_amount'],
                'tax_amount' => $totals['tax_amount'],
                'total_amount' => $totals['total_amount'],
                'ordered_quantity' => $totals['total_quantity'],
                'remaining_quantity' => $totals['total_quantity'],
                'remarks' => $attributes['remarks'] ?? null,
            ])->save();
            $this->replaceItems($order, $attributes['items'], $totals['items'], $actor);
            $this->auditLogService->record($actor, 'sales-orders', $order, 'updated', $oldValues, $order->attributesToArray());

            return $this->find($order->refresh());
        });
    }

    public function submit(SalesOrder $order, ?string $remarks, User $actor): SalesOrder
    {
        return DB::transaction(function () use ($order, $remarks, $actor): SalesOrder {
            $this->workflow->assertDraft($order);
            $this->ensureOrderIntegrity($order);
            $this->applyTransition($order, SalesOrderWorkflow::SUBMITTED, $remarks ?: 'Sales Order submitted for availability review.', $actor);

            return $this->find($order->refresh());
        });
    }

    public function confirm(SalesOrder $order, ?string $remarks, User $actor): SalesOrder
    {
        return DB::transaction(function () use ($order, $remarks, $actor): SalesOrder {
            if ($order->status !== SalesOrderWorkflow::SUBMITTED) {
                throw ValidationException::withMessages(['status' => 'Only submitted Sales Orders can be confirmed.']);
            }
            $this->ensureOrderIntegrity($order);
            $availability = $this->availability($order);
            if (! $availability['available'] && ! $this->canOverrideAvailability($actor)) {
                throw ValidationException::withMessages(['availability' => 'Insufficient finished-goods availability prevents confirmation.']);
            }

            $order->loadMissing('items');
            foreach ($order->items as $item) {
                $item->forceFill([
                    'confirmed_quantity' => $item->ordered_quantity,
                    'remaining_quantity' => max((float) $item->ordered_quantity - (float) $item->delivered_quantity, 0),
                ])->save();
            }
            $order->forceFill([
                'confirmed_quantity' => $order->ordered_quantity,
                'remaining_quantity' => max((float) $order->ordered_quantity - (float) $order->delivered_quantity, 0),
                'confirmed_at' => now(),
            ])->save();
            $this->applyTransition($order, SalesOrderWorkflow::CONFIRMED, $remarks ?: 'Sales Order confirmed after finished-goods availability check.', $actor);

            return $this->find($order->refresh());
        });
    }

    /**
     * Apply quantities dispatched by a Delivery without bypassing Sales workflow history.
     *
     * @param  array<int|string, float|int|string>  $deliveredQuantities
     */
    public function applyDeliveryProgress(SalesOrder $salesOrder, array $deliveredQuantities, User $actor, ?string $remarks = null): SalesOrder
    {
        if ($deliveredQuantities === []) {
            throw ValidationException::withMessages(['items' => 'Delivery progress must contain at least one Sales Order Item.']);
        }

        return DB::transaction(function () use ($salesOrder, $deliveredQuantities, $actor, $remarks): SalesOrder {
            $locked = SalesOrder::query()->lockForUpdate()->with('items')->findOrFail($salesOrder->getKey());
            if (! in_array($locked->status, [SalesOrderWorkflow::CONFIRMED, SalesOrderWorkflow::READY_FOR_DELIVERY], true)) {
                throw ValidationException::withMessages(['status' => 'Only open confirmed Sales Orders can receive dispatched Delivery quantities.']);
            }

            $items = $locked->items->keyBy(fn (SalesOrderItem $item): string => (string) $item->getKey());
            $oldOrderValues = $locked->attributesToArray();
            foreach ($deliveredQuantities as $itemId => $quantityValue) {
                $quantity = (float) $quantityValue;
                $item = $items->get((string) $itemId);
                if ($item === null) {
                    throw ValidationException::withMessages(['items' => 'A dispatched Delivery item does not belong to the Sales Order.']);
                }
                if ($quantity <= 0) {
                    throw ValidationException::withMessages(['items' => 'Dispatched Delivery quantities must be greater than zero.']);
                }
                $remaining = max((float) $item->remaining_quantity, 0);
                if ($quantity > $remaining + 0.0000001) {
                    throw ValidationException::withMessages(['items' => 'Dispatched Delivery quantity cannot exceed the Sales Order Item remaining quantity.']);
                }
                $oldItemValues = $item->attributesToArray();
                $item->forceFill([
                    'delivered_quantity' => (float) $item->delivered_quantity + $quantity,
                    'remaining_quantity' => max($remaining - $quantity, 0),
                ])->save();
                $this->auditLogService->record($actor, 'sales-order-items', $item, 'delivery_progressed', $oldItemValues, $item->attributesToArray());
            }

            $ordered = (float) $items->sum(fn (SalesOrderItem $item): float => (float) $item->ordered_quantity);
            $confirmed = (float) $items->sum(fn (SalesOrderItem $item): float => (float) $item->confirmed_quantity);
            $delivered = (float) $items->sum(fn (SalesOrderItem $item): float => (float) $item->delivered_quantity);
            $remaining = max($confirmed - $delivered, 0);
            $locked->forceFill([
                'ordered_quantity' => $ordered,
                'confirmed_quantity' => $confirmed,
                'delivered_quantity' => $delivered,
                'remaining_quantity' => $remaining,
            ])->save();
            $this->auditLogService->record($actor, 'sales-orders', $locked, 'delivery_progressed', $oldOrderValues, $locked->attributesToArray());

            if ($remaining <= 0.0000001) {
                $progressRemarks = $remarks ?: 'Sales Order delivery quantities are fully dispatched.';
                if ($locked->status === SalesOrderWorkflow::CONFIRMED) {
                    $this->applyTransition($locked, SalesOrderWorkflow::READY_FOR_DELIVERY, $progressRemarks, $actor);
                }
                if ($locked->status === SalesOrderWorkflow::READY_FOR_DELIVERY) {
                    $this->applyTransition($locked, SalesOrderWorkflow::DELIVERED, $progressRemarks, $actor);
                }
            }

            return $this->find($locked->refresh());
        });
    }

    public function cancel(SalesOrder $order, ?string $remarks, User $actor): SalesOrder
    {
        return DB::transaction(function () use ($order, $remarks, $actor): SalesOrder {
            $this->workflow->assertCancellable($order);
            $this->applyTransition($order, SalesOrderWorkflow::CANCELLED, $remarks ?: 'Sales Order cancelled.', $actor);

            return $this->find($order->refresh());
        });
    }

    public function transition(SalesOrder $order, string $newStatus, ?string $remarks, User $actor): SalesOrder
    {
        if (in_array($newStatus, [SalesOrderWorkflow::DRAFT, SalesOrderWorkflow::SUBMITTED, SalesOrderWorkflow::CONFIRMED, SalesOrderWorkflow::CANCELLED], true)) {
            throw ValidationException::withMessages(['status' => 'Use the dedicated Sales Order action for this status.']);
        }

        return DB::transaction(function () use ($order, $newStatus, $remarks, $actor): SalesOrder {
            $this->workflow->assertTransition($order, $newStatus);
            $this->ensureOrderIntegrity($order);
            $this->applyTransition($order, $newStatus, $remarks, $actor);

            return $this->find($order->refresh());
        });
    }

    /** @return array<string, mixed> */
    public function availability(SalesOrder $order): array
    {
        $order->loadMissing(['warehouse', 'items.product', 'items.productVariant.product', 'items.unit']);
        $lines = [];
        foreach ($order->items as $item) {
            $requiredQuantity = (float) ($item->confirmed_quantity > 0 ? $item->confirmed_quantity : $item->ordered_quantity);
            $stock = $this->inventoryService->availableStock([
                'product_id' => $item->product_id,
                'product_variant_id' => $item->product_variant_id,
                'unit_id' => $item->unit_id,
                'warehouse_id' => $order->warehouse_id,
            ]);
            $availableQuantity = $stock['quantity_available'];
            $shortageQuantity = max($requiredQuantity - $availableQuantity, 0);
            $lines[] = [
                'id' => $item->getKey(),
                'product_id' => $item->product_id,
                'product_variant_id' => $item->product_variant_id,
                'unit_id' => $item->unit_id,
                'product' => $item->product,
                'product_variant' => $item->productVariant,
                'unit' => $item->unit,
                'required_quantity' => number_format($requiredQuantity, 4, '.', ''),
                'quantity_on_hand' => number_format($stock['quantity_on_hand'], 4, '.', ''),
                'quantity_reserved' => number_format($stock['quantity_reserved'], 4, '.', ''),
                'available_quantity' => number_format($availableQuantity, 4, '.', ''),
                'shortage_quantity' => number_format($shortageQuantity, 4, '.', ''),
                'covered' => $shortageQuantity <= 0,
            ];
        }

        return [
            'sales_order_id' => $order->getKey(),
            'sales_order_number' => $order->sales_order_number,
            'warehouse' => $order->warehouse,
            'available' => collect($lines)->every(fn (array $line): bool => $line['covered']),
            'lines' => $lines,
        ];
    }

    /** @return array{status_history: Collection<int, SalesOrderStatusHistory>, audit_logs: Collection<int, AuditLog>} */
    public function history(SalesOrder $order): array
    {
        return [
            'status_history' => $order->statusHistories()->with('changer')->get(),
            'audit_logs' => AuditLog::query()
                ->with('user')
                ->where('module', 'sales-orders')
                ->where('record_id', $order->getKey())
                ->latest('id')
                ->get(),
        ];
    }

    private function canOverrideAvailability(User $actor): bool
    {
        return $actor->hasPermission('sales.override')
            && ($actor->currentAccessToken() === null || $actor->tokenCan('sales.override'));
    }

    private function ensureActiveParty(array $attributes): void
    {
        $buyerId = isset($attributes['buyer_id']) && $attributes['buyer_id'] !== '' ? (int) $attributes['buyer_id'] : null;
        $customerId = isset($attributes['customer_id']) && $attributes['customer_id'] !== '' ? (int) $attributes['customer_id'] : null;
        if (($buyerId === null) === ($customerId === null)) {
            throw ValidationException::withMessages(['party' => 'Provide exactly one active buyer or customer.']);
        }
        if ($buyerId !== null && ! Buyer::query()->whereKey($buyerId)->where('status', 'active')->exists()) {
            throw ValidationException::withMessages(['buyer_id' => 'The selected buyer must exist and be active.']);
        }
        if ($customerId !== null && ! Customer::query()->whereKey($customerId)->where('status', 'active')->exists()) {
            throw ValidationException::withMessages(['customer_id' => 'The selected customer must exist and be active.']);
        }
    }

    private function ensureActiveWarehouse(int $warehouseId): void
    {
        if (! Warehouse::query()->whereKey($warehouseId)->where('status', 'active')->exists()) {
            throw ValidationException::withMessages(['warehouse_id' => 'The selected warehouse must exist and be active.']);
        }
    }

    /** @param array<int, array<string, mixed>> $items */
    private function validateItemReferences(array $items): void
    {
        $identities = [];
        foreach ($items as $index => $item) {
            $product = Product::query()->whereKey($item['product_id'] ?? null)->where('status', 'active')->first();
            if ($product === null) {
                throw ValidationException::withMessages(["items.{$index}.product_id" => 'The selected product must exist and be active.']);
            }
            $variantId = isset($item['product_variant_id']) && $item['product_variant_id'] !== '' ? (int) $item['product_variant_id'] : null;
            $variant = null;
            if ($variantId !== null) {
                $variant = ProductVariant::query()->whereKey($variantId)->where('status', 'active')->first();
                if ($variant === null) {
                    throw ValidationException::withMessages(["items.{$index}.product_variant_id" => 'The selected product variant must exist and be active.']);
                }
                if ((int) $variant->product_id !== (int) $product->getKey()) {
                    throw ValidationException::withMessages(["items.{$index}.product_variant_id" => 'The selected variant does not belong to the selected product.']);
                }
            }
            $unitId = (int) ($item['unit_id'] ?? 0);
            if (! $product->relationLoaded('unit')) {
                $product->load('unit');
            }
            if ($product->unit === null || (int) $product->unit_id !== $unitId) {
                throw ValidationException::withMessages(["items.{$index}.unit_id" => 'The selected unit must match the product master unit.']);
            }
            $identity = $variantId !== null ? 'variant:'.$variantId : 'product:'.$product->getKey();
            if (in_array($identity, $identities, true)) {
                throw ValidationException::withMessages(['items' => 'Each product or product variant may appear only once on a Sales Order.']);
            }
            $identities[] = $identity;
        }
    }

    private function ensureOrderIntegrity(SalesOrder $order): void
    {
        $order->loadMissing(['buyer', 'customer', 'items.product', 'items.productVariant', 'items.unit', 'warehouse']);
        $this->ensureActiveParty($order->attributesToArray());
        $this->ensureActiveWarehouse((int) $order->warehouse_id);
        if ($order->items->isEmpty()) {
            throw ValidationException::withMessages(['items' => 'A Sales Order must contain at least one item.']);
        }
        if ($order->required_delivery_date->lt($order->order_date)) {
            throw ValidationException::withMessages(['required_delivery_date' => 'The required delivery date must be on or after the order date.']);
        }
        $this->validateItemReferences($order->items->map(fn (SalesOrderItem $item): array => [
            'product_id' => $item->product_id,
            'product_variant_id' => $item->product_variant_id,
            'unit_id' => $item->unit_id,
            'ordered_quantity' => $item->ordered_quantity,
            'unit_price' => $item->unit_price,
            'discount_amount' => $item->discount_amount,
            'tax_amount' => $item->tax_amount,
        ])->all());
        $this->refreshTotals($order);
    }

    private function refreshTotals(SalesOrder $order): void
    {
        $order->loadMissing('items');
        $items = $order->items->map(fn (SalesOrderItem $item): array => [
            'product_id' => $item->product_id,
            'product_variant_id' => $item->product_variant_id,
            'unit_id' => $item->unit_id,
            'ordered_quantity' => $item->ordered_quantity,
            'unit_price' => $item->unit_price,
            'discount_amount' => $item->discount_amount,
            'tax_amount' => $item->tax_amount,
            'remarks' => $item->remarks,
        ])->all();
        $totals = $this->calculationService->calculate($items, $order->order_discount_amount, $order->order_tax_amount);
        $order->forceFill([
            'subtotal' => $totals['subtotal'],
            'discount_amount' => $totals['discount_amount'],
            'tax_amount' => $totals['tax_amount'],
            'total_amount' => $totals['total_amount'],
            'ordered_quantity' => $totals['total_quantity'],
            'remaining_quantity' => max((float) $totals['total_quantity'] - (float) $order->delivered_quantity, 0),
        ])->save();
    }

    /** @param array<int, array<string, mixed>> $items */
    private function replaceItems(SalesOrder $order, array $items, array $calculatedItems, User $actor): void
    {
        $order->items()->delete();
        foreach ($items as $index => $item) {
            $calculated = $calculatedItems[$index];
            $created = $order->items()->create([
                'line_number' => $index + 1,
                'product_id' => $calculated['product_id'],
                'product_variant_id' => $calculated['product_variant_id'],
                'unit_id' => $calculated['unit_id'],
                'ordered_quantity' => $calculated['ordered_quantity'],
                'confirmed_quantity' => 0,
                'delivered_quantity' => 0,
                'remaining_quantity' => $calculated['ordered_quantity'],
                'unit_price' => $calculated['unit_price'],
                'discount_amount' => $calculated['discount_amount'],
                'tax_amount' => $calculated['tax_amount'],
                'line_total' => $calculated['line_total'],
                'remarks' => $item['remarks'] ?? null,
            ]);
            $this->auditLogService->record($actor, 'sales-order-items', $created, 'created', null, $created->attributesToArray());
        }
    }

    private function applyTransition(SalesOrder $order, string $newStatus, ?string $remarks, User $actor): SalesOrder
    {
        $oldStatus = $order->status;
        $order->forceFill(['status' => $newStatus])->save();
        $this->recordStatus($order, $oldStatus, $newStatus, $actor, $remarks);
        $this->auditLogService->record($actor, 'sales-orders', $order, 'status_changed', ['status' => $oldStatus], ['status' => $newStatus, 'remarks' => $remarks]);

        return $order;
    }

    private function recordStatus(SalesOrder $order, ?string $previousStatus, string $newStatus, User $actor, ?string $remarks): void
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
        $prefix = 'SO-'.now()->format('Ymd');
        $sequence = SalesOrder::withTrashed()->where('sales_order_number', 'like', "{$prefix}-%")->count() + 1;
        do {
            $candidate = sprintf('%s-%04d', $prefix, $sequence++);
        } while (SalesOrder::withTrashed()->where('sales_order_number', $candidate)->exists());

        return $candidate;
    }
}
