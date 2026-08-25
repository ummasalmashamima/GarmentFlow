<?php

declare(strict_types=1);

namespace App\Services\Inventory;

use App\Models\AuditLog;
use App\Models\InventoryBalance;
use App\Models\InventoryTransaction;
use App\Models\StockAdjustment;
use App\Models\StockAdjustmentItem;
use App\Models\User;
use App\Services\MasterData\AuditLogService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class StockAdjustmentService
{
    public function __construct(
        private readonly InventoryService $inventoryService,
        private readonly InventoryReferenceService $referenceService,
        private readonly AuditLogService $auditLogService,
    ) {}

    public function paginate(array $filters): LengthAwarePaginator
    {
        $query = StockAdjustment::query()->with(['warehouse', 'warehouseLocation', 'adjuster']);
        if (($filters['search'] ?? null) !== null && $filters['search'] !== '') {
            $search = (string) $filters['search'];
            $query->where(function (Builder $builder) use ($search): void {
                $builder->where('adjustment_number', 'like', "%{$search}%")
                    ->orWhere('direction', 'like', "%{$search}%")
                    ->orWhere('reason', 'like', "%{$search}%")
                    ->orWhereHas('warehouse', fn (Builder $q) => $q->where('code', 'like', "%{$search}%")->orWhere('name', 'like', "%{$search}%"));
            });
        }
        if (($filters['adjustment_direction'] ?? null) !== null && $filters['adjustment_direction'] !== '') {
            $query->where('direction', $filters['adjustment_direction']);
        }
        foreach (['status', 'warehouse_id', 'warehouse_location_id'] as $field) {
            if (($filters[$field] ?? null) !== null && $filters[$field] !== '') {
                $query->where($field, $filters[$field]);
            }
        }
        $sort = in_array(($filters['sort'] ?? 'id'), ['id', 'adjustment_number', 'adjustment_date', 'direction', 'status'], true)
            ? (string) ($filters['sort'] ?? 'id') : 'id';
        $direction = ($filters['direction_sort'] ?? $filters['direction_order'] ?? 'desc') === 'asc' ? 'asc' : 'desc';

        return $query->orderBy($sort, $direction)->paginate((int) ($filters['per_page'] ?? 15))->withQueryString();
    }

    public function find(StockAdjustment $adjustment): StockAdjustment
    {
        return $adjustment->load([
            'warehouse', 'warehouseLocation', 'adjuster',
            'items.inventoryBalance', 'items.material', 'items.product', 'items.productVariant', 'items.unit',
        ]);
    }

    public function create(array $attributes, User $actor): StockAdjustment
    {
        return DB::transaction(function () use ($attributes, $actor): StockAdjustment {
            $direction = strtoupper((string) ($attributes['direction'] ?? ''));
            if (! in_array($direction, ['IN', 'OUT'], true)) {
                throw ValidationException::withMessages(['direction' => 'Adjustment direction must be IN or OUT.']);
            }
            $warehouse = $this->referenceService->warehouse((int) $attributes['warehouse_id']);
            $location = $this->referenceService->location(isset($attributes['warehouse_location_id']) && $attributes['warehouse_location_id'] !== '' ? (int) $attributes['warehouse_location_id'] : null, $warehouse->getKey());
            $reason = trim((string) ($attributes['reason'] ?? ''));
            if ($reason === '') {
                throw ValidationException::withMessages(['reason' => 'An adjustment reason is required.']);
            }
            $items = $this->resolveItems($attributes['items'] ?? []);
            $adjustment = StockAdjustment::query()->create([
                'adjustment_number' => $this->generateNumber(),
                'direction' => $direction,
                'warehouse_id' => $warehouse->getKey(),
                'warehouse_location_id' => $location?->getKey(),
                'adjusted_by' => $actor->getKey(),
                'adjustment_date' => $attributes['adjustment_date'] ?? now(),
                'status' => 'posted',
                'reason' => $reason,
                'remarks' => $attributes['remarks'] ?? null,
            ]);
            foreach ($items as $index => $item) {
                $balance = $this->inventoryService->getOrCreateBalance($item, $warehouse->getKey(), $location?->getKey());
                $lockedBalance = InventoryBalance::query()->lockForUpdate()->findOrFail($balance->getKey());
                $adjustmentItem = $adjustment->items()->create([
                    'inventory_balance_id' => $lockedBalance->getKey(),
                    'material_id' => $item['material_id'],
                    'product_id' => $item['product_id'],
                    'product_variant_id' => $item['product_variant_id'],
                    'unit_id' => $item['unit_id'],
                    'quantity' => $item['quantity'],
                    'line_number' => $index + 1,
                    'remarks' => $item['remarks'] ?? null,
                ]);
                $transactionType = $direction === 'IN' ? InventoryService::ADJUSTMENT_IN : InventoryService::ADJUSTMENT_OUT;
                $this->inventoryService->applyLockedMovement($lockedBalance, $item, (float) $item['quantity'], $transactionType, $actor, [
                    'reference_type' => StockAdjustmentItem::class,
                    'reference_id' => $adjustmentItem->getKey(),
                    'idempotency_key' => 'stock-adjustment-item:'.$adjustmentItem->getKey(),
                    'transaction_date' => $adjustment->adjustment_date,
                    'remarks' => $reason,
                ]);
                $this->auditLogService->record($actor, 'inventory-stock-adjustments', $adjustmentItem, 'created', null, $adjustmentItem->attributesToArray());
            }
            $this->auditLogService->record($actor, 'inventory-stock-adjustments', $adjustment, 'posted', null, $adjustment->attributesToArray());

            return $this->find($adjustment->refresh());
        });
    }

    public function history(StockAdjustment $adjustment): array
    {
        return [
            'transactions' => InventoryTransaction::query()->where('reference_type', StockAdjustmentItem::class)->whereIn('reference_id', $adjustment->items()->pluck('id'))->with(['inventoryBalance', 'performer'])->latest('id')->get(),
            'audit_logs' => AuditLog::query()->where('module', 'inventory-stock-adjustments')->where('record_id', $adjustment->getKey())->latest('id')->get(),
        ];
    }

    /** @return array<int, array<string, mixed>> */
    private function resolveItems(array $items): array
    {
        if ($items === []) {
            throw ValidationException::withMessages(['items' => 'At least one Stock Adjustment item is required.']);
        }
        $resolved = [];
        foreach ($items as $index => $item) {
            $resolvedItem = $this->referenceService->item($item);
            $quantity = (float) ($item['quantity'] ?? 0);
            if ($quantity <= 0) {
                throw ValidationException::withMessages(["items.{$index}.quantity" => 'Adjustment quantity must be greater than zero.']);
            }
            $resolved[] = [...$resolvedItem, 'quantity' => $quantity, 'remarks' => $item['remarks'] ?? null];
        }

        return $resolved;
    }

    private function generateNumber(): string
    {
        $prefix = 'ADJ-'.now()->format('Ymd');
        $sequence = StockAdjustment::query()->where('adjustment_number', 'like', "{$prefix}-%")->count() + 1;
        do {
            $candidate = sprintf('%s-%04d', $prefix, $sequence++);
        } while (StockAdjustment::query()->where('adjustment_number', $candidate)->exists());

        return $candidate;
    }
}
