<?php

declare(strict_types=1);

namespace App\Services\Inventory;

use App\Models\AuditLog;
use App\Models\InventoryBalance;
use App\Models\InventoryTransaction;
use App\Models\StockTransfer;
use App\Models\StockTransferItem;
use App\Models\User;
use App\Services\MasterData\AuditLogService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class StockTransferService
{
    public function __construct(
        private readonly InventoryService $inventoryService,
        private readonly InventoryReferenceService $referenceService,
        private readonly AuditLogService $auditLogService,
    ) {}

    public function paginate(array $filters): LengthAwarePaginator
    {
        $query = StockTransfer::query()->with(['sourceWarehouse', 'sourceLocation', 'destinationWarehouse', 'destinationLocation', 'transferor']);
        if (($filters['search'] ?? null) !== null && $filters['search'] !== '') {
            $search = (string) $filters['search'];
            $query->where(function (Builder $builder) use ($search): void {
                $builder->where('transfer_number', 'like', "%{$search}%")
                    ->orWhere('remarks', 'like', "%{$search}%")
                    ->orWhereHas('sourceWarehouse', fn (Builder $q) => $q->where('code', 'like', "%{$search}%")->orWhere('name', 'like', "%{$search}%"))
                    ->orWhereHas('destinationWarehouse', fn (Builder $q) => $q->where('code', 'like', "%{$search}%")->orWhere('name', 'like', "%{$search}%"));
            });
        }
        foreach (['status', 'source_warehouse_id', 'destination_warehouse_id'] as $field) {
            if (($filters[$field] ?? null) !== null && $filters[$field] !== '') {
                $query->where($field, $filters[$field]);
            }
        }
        foreach ([
            'transfer_date_from' => ['transfer_date', '>='],
            'transfer_date_to' => ['transfer_date', '<='],
        ] as $filter => [$column, $operator]) {
            if (($filters[$filter] ?? null) !== null && $filters[$filter] !== '') {
                $query->where($column, $operator, $filters[$filter]);
            }
        }
        $sort = in_array(($filters['sort'] ?? 'id'), ['id', 'transfer_number', 'transfer_date', 'status'], true)
            ? (string) ($filters['sort'] ?? 'id') : 'id';
        $direction = ($filters['direction'] ?? 'desc') === 'asc' ? 'asc' : 'desc';

        return $query->orderBy($sort, $direction)->paginate((int) ($filters['per_page'] ?? 15))->withQueryString();
    }

    public function find(StockTransfer $transfer): StockTransfer
    {
        return $transfer->load([
            'sourceWarehouse', 'sourceLocation', 'destinationWarehouse', 'destinationLocation', 'transferor',
            'items.sourceBalance', 'items.destinationBalance', 'items.material', 'items.product', 'items.productVariant', 'items.unit',
        ]);
    }

    public function create(array $attributes, User $actor): StockTransfer
    {
        return DB::transaction(function () use ($attributes, $actor): StockTransfer {
            $sourceWarehouse = $this->referenceService->warehouse((int) $attributes['source_warehouse_id']);
            $destinationWarehouse = $this->referenceService->warehouse((int) $attributes['destination_warehouse_id']);
            $sourceLocation = $this->referenceService->location(isset($attributes['source_location_id']) && $attributes['source_location_id'] !== '' ? (int) $attributes['source_location_id'] : null, $sourceWarehouse->getKey());
            $destinationLocation = $this->referenceService->location(isset($attributes['destination_location_id']) && $attributes['destination_location_id'] !== '' ? (int) $attributes['destination_location_id'] : null, $destinationWarehouse->getKey());
            if ($sourceWarehouse->getKey() === $destinationWarehouse->getKey() && $sourceLocation?->getKey() === $destinationLocation?->getKey()) {
                throw ValidationException::withMessages(['destination_warehouse_id' => 'The source and destination stock locations must be different.']);
            }
            $items = $this->resolveItems($attributes['items'] ?? []);
            $transfer = StockTransfer::query()->create([
                'transfer_number' => $this->generateNumber(),
                'source_warehouse_id' => $sourceWarehouse->getKey(),
                'source_location_id' => $sourceLocation?->getKey(),
                'destination_warehouse_id' => $destinationWarehouse->getKey(),
                'destination_location_id' => $destinationLocation?->getKey(),
                'transferred_by' => $actor->getKey(),
                'transfer_date' => $attributes['transfer_date'] ?? now(),
                'status' => 'posted',
                'remarks' => $attributes['remarks'] ?? null,
            ]);

            $balancePairs = [];
            foreach ($items as $index => $item) {
                $source = $this->inventoryService->getOrCreateBalance($item, $sourceWarehouse->getKey(), $sourceLocation?->getKey());
                $destination = $this->inventoryService->getOrCreateBalance($item, $destinationWarehouse->getKey(), $destinationLocation?->getKey());
                $balancePairs[$source->getKey()] = true;
                $balancePairs[$destination->getKey()] = true;
            }
            $balances = InventoryBalance::query()->whereIn('id', array_keys($balancePairs))->orderBy('id')->lockForUpdate()->get()->keyBy('id');

            foreach ($items as $index => $item) {
                $source = $this->inventoryService->getOrCreateBalance($item, $sourceWarehouse->getKey(), $sourceLocation?->getKey());
                $destination = $this->inventoryService->getOrCreateBalance($item, $destinationWarehouse->getKey(), $destinationLocation?->getKey());
                $transferItem = $transfer->items()->create([
                    'source_inventory_balance_id' => $source->getKey(),
                    'destination_inventory_balance_id' => $destination->getKey(),
                    'material_id' => $item['material_id'],
                    'product_id' => $item['product_id'],
                    'product_variant_id' => $item['product_variant_id'],
                    'unit_id' => $item['unit_id'],
                    'quantity' => $item['quantity'],
                    'line_number' => $index + 1,
                    'remarks' => $item['remarks'] ?? null,
                ]);
                $sourceLocked = $balances->get($source->getKey());
                $destinationLocked = $balances->get($destination->getKey());
                if ($sourceLocked === null || $destinationLocked === null) {
                    throw ValidationException::withMessages(['items' => 'Transfer balances could not be locked.']);
                }
                $this->inventoryService->applyLockedMovement($sourceLocked, $item, (float) $item['quantity'], InventoryService::TRANSFER_OUT, $actor, [
                    'reference_type' => StockTransferItem::class,
                    'reference_id' => $transferItem->getKey(),
                    'idempotency_key' => 'stock-transfer-item:'.$transferItem->getKey().':out',
                    'transaction_date' => $transfer->transfer_date,
                    'remarks' => 'Transfer out from '.$transfer->transfer_number.'.',
                ]);
                $this->inventoryService->applyLockedMovement($destinationLocked, $item, (float) $item['quantity'], InventoryService::TRANSFER_IN, $actor, [
                    'reference_type' => StockTransferItem::class,
                    'reference_id' => $transferItem->getKey(),
                    'idempotency_key' => 'stock-transfer-item:'.$transferItem->getKey().':in',
                    'transaction_date' => $transfer->transfer_date,
                    'remarks' => 'Transfer in from '.$transfer->transfer_number.'.',
                ]);
                $this->auditLogService->record($actor, 'inventory-stock-transfers', $transferItem, 'created', null, $transferItem->attributesToArray());
            }
            $this->auditLogService->record($actor, 'inventory-stock-transfers', $transfer, 'posted', null, $transfer->attributesToArray());

            return $this->find($transfer->refresh());
        });
    }

    public function history(StockTransfer $transfer): array
    {
        return [
            'transactions' => InventoryTransaction::query()->where('reference_type', StockTransferItem::class)->whereIn('reference_id', $transfer->items()->pluck('id'))->with(['inventoryBalance', 'performer'])->latest('id')->get(),
            'audit_logs' => AuditLog::query()->where('module', 'inventory-stock-transfers')->where('record_id', $transfer->getKey())->latest('id')->get(),
        ];
    }

    /** @return array<int, array<string, mixed>> */
    private function resolveItems(array $items): array
    {
        if ($items === []) {
            throw ValidationException::withMessages(['items' => 'At least one Stock Transfer item is required.']);
        }
        $resolved = [];
        foreach ($items as $index => $item) {
            $resolvedItem = $this->referenceService->item($item);
            $quantity = (float) ($item['quantity'] ?? 0);
            if ($quantity <= 0) {
                throw ValidationException::withMessages(["items.{$index}.quantity" => 'Transfer quantity must be greater than zero.']);
            }
            $resolved[] = [...$resolvedItem, 'quantity' => $quantity, 'remarks' => $item['remarks'] ?? null];
        }

        return $resolved;
    }

    private function generateNumber(): string
    {
        $prefix = 'TRF-'.now()->format('Ymd');
        $sequence = StockTransfer::query()->where('transfer_number', 'like', "{$prefix}-%")->count() + 1;
        do {
            $candidate = sprintf('%s-%04d', $prefix, $sequence++);
        } while (StockTransfer::query()->where('transfer_number', $candidate)->exists());

        return $candidate;
    }
}
