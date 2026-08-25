<?php

declare(strict_types=1);

namespace App\Services\Production;

use App\Models\MaterialConsumption;
use App\Models\ProductionOrder;
use App\Models\ProductionOrderItem;
use App\Models\User;
use App\Services\Inventory\InventoryService;
use App\Services\MasterData\AuditLogService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class MaterialConsumptionService
{
    public function __construct(
        private readonly InventoryService $inventoryService,
        private readonly AuditLogService $auditLogService,
    ) {}

    public function paginate(array $filters): LengthAwarePaginator
    {
        $query = MaterialConsumption::query()->with(['productionOrder', 'productionOrderItem', 'material', 'unit', 'inventoryTransaction', 'recorder']);
        if (($filters['search'] ?? null) !== null && $filters['search'] !== '') {
            $search = (string) $filters['search'];
            $query->where(function (Builder $builder) use ($search): void {
                $builder->where('consumption_number', 'like', "%{$search}%")
                    ->orWhere('remarks', 'like', "%{$search}%")
                    ->orWhereHas('productionOrder', fn (Builder $q) => $q->where('order_number', 'like', "%{$search}%"))
                    ->orWhereHas('material', fn (Builder $q) => $q->where('code', 'like', "%{$search}%")->orWhere('name', 'like', "%{$search}%"));
            });
        }
        foreach (['production_order_id', 'production_order_item_id', 'material_id', 'recorded_by'] as $field) {
            if (($filters[$field] ?? null) !== null && $filters[$field] !== '') {
                $query->where($field, $filters[$field]);
            }
        }
        foreach ([
            'date_from' => ['consumption_date', '>='],
            'date_to' => ['consumption_date', '<='],
        ] as $filter => [$column, $operator]) {
            if (($filters[$filter] ?? null) !== null && $filters[$filter] !== '') {
                $query->where($column, $operator, $filters[$filter]);
            }
        }
        $sort = in_array(($filters['sort'] ?? 'id'), ['id', 'consumption_number', 'quantity', 'consumption_date', 'created_at'], true)
            ? (string) ($filters['sort'] ?? 'id') : 'id';
        $direction = ($filters['direction'] ?? 'desc') === 'asc' ? 'asc' : 'desc';

        return $query->orderBy($sort, $direction)->paginate((int) ($filters['per_page'] ?? 15))->withQueryString();
    }

    public function consume(ProductionOrder $order, array $attributes, User $actor): MaterialConsumption
    {
        return DB::transaction(function () use ($order, $attributes, $actor): MaterialConsumption {
            $lockedOrder = ProductionOrder::query()->lockForUpdate()->findOrFail($order->getKey());
            if ($lockedOrder->status !== ProductionWorkflow::IN_PROGRESS) {
                throw ValidationException::withMessages(['status' => 'Material can only be consumed while the Production Order is in progress.']);
            }
            $item = ProductionOrderItem::query()->lockForUpdate()->where('production_order_id', $lockedOrder->getKey())->find((int) ($attributes['production_order_item_id'] ?? 0));
            if ($item === null) {
                throw ValidationException::withMessages(['production_order_item_id' => 'The selected material line does not belong to this Production Order.']);
            }
            $quantity = (float) ($attributes['quantity'] ?? 0);
            if ($quantity <= 0) {
                throw ValidationException::withMessages(['quantity' => 'Consumption quantity must be greater than zero.']);
            }
            $remaining = max((float) $item->required_quantity - (float) $item->consumed_quantity, 0);
            if ($quantity > $remaining && ! $actor->hasPermission('production.override')) {
                throw ValidationException::withMessages(['quantity' => 'Consumption cannot exceed the remaining BOM requirement without production.override.']);
            }
            $date = $attributes['consumption_date'] ?? now()->toDateString();
            $idempotencyKey = $attributes['idempotency_key'] ?? sprintf('production-order-item:%d:consumption:%s:%s', $item->getKey(), $date, number_format($quantity, 4, '.', ''));
            $existing = MaterialConsumption::query()->where('idempotency_key', $idempotencyKey)->first();
            if ($existing !== null) {
                return $existing->load(['productionOrder', 'productionOrderItem', 'material', 'unit', 'inventoryTransaction', 'recorder']);
            }

            $consumption = MaterialConsumption::query()->create([
                'consumption_number' => $this->generateNumber(),
                'production_order_id' => $lockedOrder->getKey(),
                'production_order_item_id' => $item->getKey(),
                'material_id' => $item->material_id,
                'unit_id' => $item->unit_id,
                'quantity' => $quantity,
                'inventory_transaction_id' => null,
                'idempotency_key' => $idempotencyKey,
                'consumption_date' => $date,
                'recorded_by' => $actor->getKey(),
                'remarks' => $attributes['remarks'] ?? null,
            ]);
            $movement = $this->inventoryService->stockOut([
                'material_id' => $item->material_id,
                'unit_id' => $item->unit_id,
                'warehouse_id' => $lockedOrder->issue_warehouse_id,
                'warehouse_location_id' => $lockedOrder->issue_warehouse_location_id,
                'quantity' => $quantity,
                'transaction_date' => $date,
                'reference_type' => MaterialConsumption::class,
                'reference_id' => $consumption->getKey(),
                'idempotency_key' => 'production-consumption:'.$idempotencyKey,
                'remarks' => 'Material consumption for Production Order '.$lockedOrder->order_number.'.',
            ], $actor);
            $consumption->update(['inventory_transaction_id' => $movement['transaction']->getKey()]);
            $item->increment('consumed_quantity', $quantity);
            $item->refresh();
            $this->auditLogService->record($actor, 'material-consumptions', $consumption, 'posted', null, $consumption->fresh()->attributesToArray());

            return $consumption->fresh()->load(['productionOrder', 'productionOrderItem', 'material', 'unit', 'inventoryTransaction', 'recorder']);
        });
    }

    private function generateNumber(): string
    {
        $base = 'MC-'.now()->format('Ymd');
        $sequence = MaterialConsumption::query()->where('consumption_number', 'like', "{$base}-%")->count() + 1;
        do {
            $candidate = sprintf('%s-%04d', $base, $sequence++);
        } while (MaterialConsumption::query()->where('consumption_number', $candidate)->exists());

        return $candidate;
    }
}
