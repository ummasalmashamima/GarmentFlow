<?php

declare(strict_types=1);

namespace App\Services\Delivery;

use App\Models\Delivery;
use App\Models\DeliveryItem;
use App\Models\SalesOrder;
use App\Models\SalesOrderItem;
use App\Models\User;
use App\Services\Inventory\InventoryService;
use App\Services\MasterData\AuditLogService;
use App\Services\Sales\SalesOrderService;
use App\Services\Sales\SalesOrderWorkflow;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class DeliveryService
{
    public function __construct(
        private readonly DeliveryWorkflow $workflow,
        private readonly ShipmentTrackingService $trackingService,
        private readonly InventoryService $inventoryService,
        private readonly AuditLogService $auditLogService,
        private readonly SalesOrderService $salesOrderService,
    ) {}

    /** @param array<string, mixed> $filters */
    public function list(array $filters = []): LengthAwarePaginator
    {
        $query = Delivery::query()->with(['salesOrder.buyer', 'salesOrder.customer', 'warehouse', 'creator']);
        if (($filters['search'] ?? null) !== null && $filters['search'] !== '') {
            $search = (string) $filters['search'];
            $query->where(function (Builder $builder) use ($search): void {
                $builder->where('delivery_number', 'like', "%{$search}%")
                    ->orWhere('tracking_number', 'like', "%{$search}%")
                    ->orWhereHas('salesOrder', function (Builder $salesOrder) use ($search): void {
                        $salesOrder->where('sales_order_number', 'like', "%{$search}%")
                            ->orWhereHas('buyer', fn (Builder $buyer) => $buyer->where('name', 'like', "%{$search}%"))
                            ->orWhereHas('customer', fn (Builder $customer) => $customer->where('name', 'like', "%{$search}%"));
                    });
            });
        }
        foreach (['sales_order_id', 'warehouse_id', 'status'] as $field) {
            if (($filters[$field] ?? null) !== null && $filters[$field] !== '') {
                $query->where($field, $filters[$field]);
            }
        }
        foreach ([['delivery_date_from', '>='], ['delivery_date_to', '<='], ['expected_delivery_date_from', '>='], ['expected_delivery_date_to', '<=']] as [$field, $operator]) {
            $column = str_replace(['_from', '_to'], '', (string) $field);
            if (($filters[$field] ?? null) !== null && $filters[$field] !== '') {
                $query->whereDate($column, $operator, $filters[$field]);
            }
        }
        $sort = in_array($filters['sort'] ?? 'id', ['id', 'delivery_number', 'delivery_date', 'expected_delivery_date', 'status', 'ordered_quantity', 'dispatched_quantity', 'delivered_quantity'], true) ? $filters['sort'] : 'id';
        $direction = ($filters['direction'] ?? 'desc') === 'asc' ? 'asc' : 'desc';

        return $query->orderBy($sort, $direction)->paginate((int) ($filters['per_page'] ?? 10))->withQueryString();
    }

    public function find(Delivery $delivery): Delivery
    {
        return $delivery->load([
            'salesOrder.buyer',
            'salesOrder.customer',
            'salesOrder.warehouse',
            'warehouse',
            'creator',
            'items.salesOrderItem',
            'items.product',
            'items.productVariant.product',
            'items.unit',
            'items.inventoryTransaction',
            'trackingHistories.changer',
        ]);
    }

    /** @param array<string, mixed> $attributes */
    public function create(array $attributes, User $actor): Delivery
    {
        return DB::transaction(function () use ($attributes, $actor): Delivery {
            $salesOrder = SalesOrder::query()->lockForUpdate()->with(['items.product', 'items.productVariant', 'items.unit'])->findOrFail((int) $attributes['sales_order_id']);
            $this->assertSalesOrderCanReceiveDelivery($salesOrder);
            $requestedItems = $attributes['items'] ?? [];
            if ($requestedItems === []) {
                throw ValidationException::withMessages(['items' => 'A Delivery must contain at least one item.']);
            }
            $warehouseId = (int) ($attributes['warehouse_id'] ?? $salesOrder->warehouse_id);
            if ($warehouseId !== (int) $salesOrder->warehouse_id) {
                throw ValidationException::withMessages(['warehouse_id' => 'Delivery warehouse must match the Sales Order warehouse.']);
            }
            $sourceItems = $salesOrder->items->keyBy(fn (SalesOrderItem $item): string => (string) $item->getKey());
            $usedSourceItems = [];
            $deliveryQuantity = 0.0;
            $calculated = [];
            foreach ($requestedItems as $index => $requested) {
                $sourceItemId = (int) ($requested['sales_order_item_id'] ?? 0);
                if (isset($usedSourceItems[$sourceItemId])) {
                    throw ValidationException::withMessages(["items.{$index}.sales_order_item_id" => 'Each Sales Order Item may appear only once per Delivery.']);
                }
                $sourceItem = $sourceItems->get((string) $sourceItemId);
                if ($sourceItem === null) {
                    throw ValidationException::withMessages(["items.{$index}.sales_order_item_id" => 'The Sales Order Item does not belong to the selected Sales Order.']);
                }
                $quantity = (float) ($requested['delivery_quantity'] ?? 0);
                if ($quantity <= 0) {
                    throw ValidationException::withMessages(["items.{$index}.delivery_quantity" => 'Delivery quantity must be greater than zero.']);
                }
                $remaining = $this->sourceRemainingQuantity($sourceItem);
                if ($quantity > $remaining + 0.0000001) {
                    throw ValidationException::withMessages(["items.{$index}.delivery_quantity" => 'Delivery quantity cannot exceed the Sales Order Item remaining quantity.']);
                }
                $this->assertSourceIdentity($sourceItem, $requested, $index);
                $usedSourceItems[$sourceItemId] = true;
                $deliveryQuantity += $quantity;
                $calculated[] = ['source' => $sourceItem, 'quantity' => $quantity, 'remarks' => $requested['remarks'] ?? null];
            }
            $delivery = Delivery::query()->create([
                'delivery_number' => $this->generateDeliveryNumber(),
                'sales_order_id' => $salesOrder->getKey(),
                'warehouse_id' => $warehouseId,
                'status' => DeliveryWorkflow::CREATED,
                'delivery_date' => $attributes['delivery_date'],
                'expected_delivery_date' => $attributes['expected_delivery_date'] ?? null,
                'ordered_quantity' => $deliveryQuantity,
                'dispatched_quantity' => 0,
                'delivered_quantity' => 0,
                'remaining_quantity' => $deliveryQuantity,
                'carrier_name' => $attributes['carrier_name'] ?? null,
                'tracking_number' => $attributes['tracking_number'] ?? null,
                'delivery_address' => $attributes['delivery_address'] ?? $salesOrder->delivery_address,
                'contact_information' => $attributes['contact_information'] ?? $salesOrder->contact_information,
                'remarks' => $attributes['remarks'] ?? null,
                'created_by' => $actor->getKey(),
            ]);
            foreach ($calculated as $index => $line) {
                /** @var SalesOrderItem $source */
                $source = $line['source'];
                $delivery->items()->create([
                    'sales_order_item_id' => $source->getKey(),
                    'line_number' => $index + 1,
                    'product_id' => $source->product_id,
                    'product_variant_id' => $source->product_variant_id,
                    'unit_id' => $source->unit_id,
                    'delivery_quantity' => $line['quantity'],
                    'dispatched_quantity' => 0,
                    'delivered_quantity' => 0,
                    'remaining_quantity' => $line['quantity'],
                    'remarks' => $line['remarks'],
                ]);
            }
            $this->auditLogService->record($actor, 'deliveries', $delivery, 'created', null, $delivery->attributesToArray());
            $this->trackingService->record($delivery, null, DeliveryWorkflow::CREATED, ['remarks' => 'Delivery created from confirmed Sales Order.'], $actor);

            return $this->find($delivery);
        });
    }

    /** @param array<string, mixed> $attributes */
    public function update(Delivery $delivery, array $attributes, User $actor): Delivery
    {
        return DB::transaction(function () use ($delivery, $attributes, $actor): Delivery {
            $locked = Delivery::query()->lockForUpdate()->findOrFail($delivery->getKey());
            $this->workflow->assertMutable($locked);
            if ((float) $locked->dispatched_quantity > 0) {
                throw ValidationException::withMessages(['status' => 'Dispatched deliveries cannot be edited.']);
            }
            $locked->forceFill(array_filter([
                'delivery_date' => $attributes['delivery_date'] ?? null,
                'expected_delivery_date' => $attributes['expected_delivery_date'] ?? null,
                'carrier_name' => $attributes['carrier_name'] ?? null,
                'tracking_number' => $attributes['tracking_number'] ?? null,
                'delivery_address' => $attributes['delivery_address'] ?? null,
                'contact_information' => $attributes['contact_information'] ?? null,
                'remarks' => $attributes['remarks'] ?? null,
            ], static fn (mixed $value): bool => $value !== null))->save();
            $this->auditLogService->record($actor, 'deliveries', $locked, 'updated', null, $locked->attributesToArray());

            return $this->find($locked);
        });
    }

    public function dispatch(Delivery $delivery, User $actor): Delivery
    {
        return DB::transaction(function () use ($delivery, $actor): Delivery {
            $locked = Delivery::query()->lockForUpdate()->with(['items.salesOrderItem'])->findOrFail($delivery->getKey());
            $this->workflow->assertDispatchable($locked);
            $previousStatus = $locked->status;
            $salesOrder = SalesOrder::query()->lockForUpdate()->with('items')->findOrFail($locked->sales_order_id);
            $this->assertSalesOrderCanDispatchDelivery($salesOrder);
            $sourceItems = $salesOrder->items->keyBy(fn (SalesOrderItem $item): string => (string) $item->getKey());
            $salesProgress = [];
            foreach ($locked->items as $item) {
                $source = $sourceItems->get((string) $item->sales_order_item_id);
                if ($source === null) {
                    throw ValidationException::withMessages(['items' => 'A Delivery item no longer belongs to its Sales Order.']);
                }
                $quantity = (float) $item->delivery_quantity;
                $result = $this->inventoryService->stockOut([
                    'product_id' => $item->product_id,
                    'product_variant_id' => $item->product_variant_id,
                    'unit_id' => $item->unit_id,
                    'warehouse_id' => $locked->warehouse_id,
                    'quantity' => $quantity,
                    'transaction_date' => now()->toDateTimeString(),
                    'reference_type' => DeliveryItem::class,
                    'reference_id' => $item->getKey(),
                    'idempotency_key' => 'delivery-item:'.$item->getKey().':dispatch',
                    'remarks' => 'Dispatch for '.$locked->delivery_number.'.',
                ], $actor);
                $item->forceFill([
                    'dispatched_quantity' => $quantity,
                    'remaining_quantity' => 0,
                    'inventory_transaction_id' => $result['transaction']->getKey(),
                ])->save();
                $salesProgress[$source->getKey()] = ($salesProgress[$source->getKey()] ?? 0) + $quantity;
            }
            $locked->forceFill([
                'status' => DeliveryWorkflow::SHIPPED,
                'dispatched_at' => now(),
                'dispatched_quantity' => $locked->items->sum(fn (DeliveryItem $item): float => (float) $item->delivery_quantity),
                'remaining_quantity' => 0,
            ])->save();
            $this->salesOrderService->applyDeliveryProgress($salesOrder, $salesProgress, $actor, 'Sales Order progress updated by Delivery dispatch.');
            $this->trackingService->record($locked, $previousStatus, DeliveryWorkflow::SHIPPED, ['remarks' => 'Delivery dispatched through InventoryService.'], $actor);
            $this->auditLogService->record($actor, 'deliveries', $locked, 'dispatched', null, $locked->attributesToArray());

            return $this->find($locked);
        });
    }

    public function transition(Delivery $delivery, string $newStatus, ?string $remarks, User $actor): Delivery
    {
        if ($newStatus === DeliveryWorkflow::SHIPPED) {
            throw ValidationException::withMessages(['status' => 'Use the dispatch endpoint to move a Delivery to shipped and deduct Inventory.']);
        }

        return DB::transaction(function () use ($delivery, $newStatus, $remarks, $actor): Delivery {
            $locked = Delivery::query()->lockForUpdate()->findOrFail($delivery->getKey());
            $this->workflow->assertTransition($locked, $newStatus);
            $previous = $locked->status;
            $locked->forceFill(['status' => $newStatus])->save();
            if ($newStatus === DeliveryWorkflow::DELIVERED) {
                $locked->forceFill(['delivered_at' => now(), 'delivered_quantity' => $locked->dispatched_quantity, 'remaining_quantity' => 0])->save();
                $locked->items()->update(['delivered_quantity' => DB::raw('dispatched_quantity'), 'remaining_quantity' => 0]);
            }
            $this->trackingService->record($locked, $previous, $newStatus, ['remarks' => $remarks], $actor);
            $this->auditLogService->record($actor, 'deliveries', $locked, 'status_changed', ['status' => $previous], ['status' => $newStatus, 'remarks' => $remarks]);

            return $this->find($locked);
        });
    }

    public function complete(Delivery $delivery, ?string $remarks, User $actor): Delivery
    {
        return $this->transition($delivery, DeliveryWorkflow::COMPLETED, $remarks, $actor);
    }

    public function history(Delivery $delivery): array
    {
        return [
            'tracking_history' => $delivery->trackingHistories()->with('changer')->latest('id')->get(),
            'audit_logs' => $this->auditLogService->forRecord('deliveries', $delivery->getKey()),
        ];
    }

    private function assertSalesOrderCanReceiveDelivery(SalesOrder $salesOrder): void
    {
        if ($salesOrder->status !== SalesOrderWorkflow::CONFIRMED) {
            throw ValidationException::withMessages(['sales_order_id' => 'Only confirmed Sales Orders can receive a Delivery.']);
        }
        if ((float) $salesOrder->remaining_quantity <= 0) {
            throw ValidationException::withMessages(['sales_order_id' => 'Completed Sales Orders cannot receive new Deliveries.']);
        }
    }

    private function assertSalesOrderCanDispatchDelivery(SalesOrder $salesOrder): void
    {
        if (! in_array($salesOrder->status, [SalesOrderWorkflow::CONFIRMED, SalesOrderWorkflow::READY_FOR_DELIVERY], true)) {
            throw ValidationException::withMessages(['sales_order_id' => 'Only open confirmed Sales Orders can dispatch a Delivery.']);
        }
        if ((float) $salesOrder->remaining_quantity <= 0) {
            throw ValidationException::withMessages(['sales_order_id' => 'The Sales Order has no remaining quantity to dispatch.']);
        }
    }

    private function sourceRemainingQuantity(SalesOrderItem $source): float
    {
        $alreadyDelivered = (float) $source->deliveryItems()->whereHas('delivery', fn (Builder $delivery) => $delivery->whereNotIn('status', [DeliveryWorkflow::CANCELLED, DeliveryWorkflow::FAILED, DeliveryWorkflow::RETURNED]))->sum('delivery_quantity');

        return max((float) $source->confirmed_quantity - $alreadyDelivered, 0);
    }

    /** @param array<string, mixed> $requested */
    private function assertSourceIdentity(SalesOrderItem $source, array $requested, int $index): void
    {
        foreach (['product_id', 'product_variant_id', 'unit_id'] as $field) {
            $requestedValue = $requested[$field] ?? null;
            if ($requestedValue !== null && $requestedValue !== '' && (int) $requestedValue !== (int) $source->{$field}) {
                throw ValidationException::withMessages(["items.{$index}.{$field}" => 'Delivery identity must match the Sales Order Item.']);
            }
        }
    }

    private function generateDeliveryNumber(): string
    {
        $prefix = 'DLV-'.now()->format('Ymd').'-';
        $last = Delivery::withTrashed()->where('delivery_number', 'like', $prefix.'%')->orderByDesc('id')->value('delivery_number');
        $sequence = $last ? ((int) substr((string) $last, -4)) + 1 : 1;

        return $prefix.str_pad((string) $sequence, 4, '0', STR_PAD_LEFT);
    }
}
