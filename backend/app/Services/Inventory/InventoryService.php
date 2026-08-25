<?php

declare(strict_types=1);

namespace App\Services\Inventory;

use App\Models\GoodsReceipt;
use App\Models\GoodsReceiptItem;
use App\Models\InventoryBalance;
use App\Models\InventoryTransaction;
use App\Models\User;
use App\Services\MasterData\AuditLogService;
use App\Services\Procurement\ProcurementWorkflow;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class InventoryService
{
    public const STOCK_IN = 'STOCK_IN';

    public const STOCK_OUT = 'STOCK_OUT';

    public const TRANSFER_IN = 'TRANSFER_IN';

    public const TRANSFER_OUT = 'TRANSFER_OUT';

    public const ADJUSTMENT_IN = 'ADJUSTMENT_IN';

    public const ADJUSTMENT_OUT = 'ADJUSTMENT_OUT';

    public function __construct(
        private readonly InventoryReferenceService $referenceService,
        private readonly AuditLogService $auditLogService,
    ) {}

    public function paginate(array $filters): LengthAwarePaginator
    {
        $query = InventoryBalance::query()->with(['warehouse', 'warehouseLocation', 'material', 'product', 'productVariant', 'unit']);
        $this->applyBalanceFilters($query, $filters);
        $sort = in_array(($filters['sort'] ?? 'id'), ['id', 'stock_key', 'quantity_on_hand', 'quantity_reserved', 'created_at'], true)
            ? (string) ($filters['sort'] ?? 'id') : 'id';
        $direction = ($filters['direction'] ?? 'desc') === 'asc' ? 'asc' : 'desc';

        return $query->orderBy($sort, $direction)->paginate((int) ($filters['per_page'] ?? 15))->withQueryString();
    }

    public function find(InventoryBalance $balance): InventoryBalance
    {
        return $balance->load([
            'warehouse',
            'warehouseLocation',
            'material',
            'product',
            'productVariant.product',
            'unit',
            'transactions' => fn ($query) => $query->with(['performer', 'warehouseLocation'])->latest('transaction_date')->latest('id')->limit(50),
        ]);
    }

    public function warehouseStock(int $warehouseId, array $filters): LengthAwarePaginator
    {
        return $this->paginate([...$filters, 'warehouse_id' => $warehouseId]);
    }

    public function locationStock(int $locationId, array $filters): LengthAwarePaginator
    {
        $location = $this->referenceService->locationModel($locationId);

        return $this->paginate([...$filters, 'warehouse_id' => $location->warehouse_id, 'warehouse_location_id' => $locationId]);
    }

    /** @return array<string, int|float> */
    public function summary(array $filters): array
    {
        $query = InventoryBalance::query();
        $this->applyBalanceFilters($query, $filters);
        $row = $query->selectRaw('COUNT(*) AS balance_count, COALESCE(SUM(quantity_on_hand), 0) AS quantity_on_hand, COALESCE(SUM(quantity_reserved), 0) AS quantity_reserved, COALESCE(SUM(quantity_on_hand - quantity_reserved), 0) AS quantity_available')->first();

        return [
            'balance_count' => (int) ($row->balance_count ?? 0),
            'quantity_on_hand' => (float) ($row->quantity_on_hand ?? 0),
            'quantity_reserved' => (float) ($row->quantity_reserved ?? 0),
            'quantity_available' => (float) ($row->quantity_available ?? 0),
        ];
    }

    public function transactionHistory(array $filters): LengthAwarePaginator
    {
        $query = InventoryTransaction::query()->with(['inventoryBalance', 'warehouse', 'warehouseLocation', 'material', 'product', 'productVariant', 'unit', 'performer']);
        if (($filters['search'] ?? null) !== null && $filters['search'] !== '') {
            $search = (string) $filters['search'];
            $query->where(function (Builder $builder) use ($search): void {
                $builder->where('transaction_number', 'like', "%{$search}%")
                    ->orWhere('transaction_type', 'like', "%{$search}%")
                    ->orWhere('reference_type', 'like', "%{$search}%")
                    ->orWhereHas('material', fn (Builder $q) => $q->where('code', 'like', "%{$search}%")->orWhere('name', 'like', "%{$search}%"))
                    ->orWhereHas('product', fn (Builder $q) => $q->where('code', 'like', "%{$search}%")->orWhere('name', 'like', "%{$search}%"))
                    ->orWhereHas('productVariant', fn (Builder $q) => $q->where('sku', 'like', "%{$search}%"));
            });
        }
        foreach (['transaction_type', 'warehouse_id', 'warehouse_location_id', 'material_id', 'product_id', 'product_variant_id', 'performed_by'] as $field) {
            if (($filters[$field] ?? null) !== null && $filters[$field] !== '') {
                $query->where($field, $filters[$field]);
            }
        }
        foreach ([
            'transaction_date_from' => ['transaction_date', '>='],
            'transaction_date_to' => ['transaction_date', '<='],
        ] as $filter => [$column, $operator]) {
            if (($filters[$filter] ?? null) !== null && $filters[$filter] !== '') {
                $query->where($column, $operator, $filters[$filter]);
            }
        }
        $sort = in_array(($filters['sort'] ?? 'id'), ['id', 'transaction_number', 'transaction_date', 'quantity', 'transaction_type'], true)
            ? (string) ($filters['sort'] ?? 'id') : 'id';
        $direction = ($filters['direction'] ?? 'desc') === 'asc' ? 'asc' : 'desc';

        return $query->orderBy($sort, $direction)->paginate((int) ($filters['per_page'] ?? 15))->withQueryString();
    }

    /**
     * @param  array<string, mixed>  $attributes
     * @return array{balance: InventoryBalance|null, quantity_on_hand: float, quantity_reserved: float, quantity_available: float, item: array<string, mixed>}
     */
    public function availableStock(array $attributes): array
    {
        $item = $this->referenceService->item($attributes);
        $warehouse = $this->referenceService->warehouse((int) $attributes['warehouse_id']);
        $location = $this->referenceService->location(isset($attributes['warehouse_location_id']) && $attributes['warehouse_location_id'] !== '' ? (int) $attributes['warehouse_location_id'] : null, $warehouse->getKey());
        $stockKey = $this->referenceService->stockKey($item, $warehouse->getKey(), $location?->getKey());
        $balance = InventoryBalance::query()->with(['warehouse', 'warehouseLocation', 'material', 'product', 'productVariant.product', 'unit'])->where('stock_key', $stockKey)->first();
        $quantityOnHand = (float) ($balance?->quantity_on_hand ?? 0);
        $quantityReserved = (float) ($balance?->quantity_reserved ?? 0);

        return [
            'balance' => $balance,
            'quantity_on_hand' => $quantityOnHand,
            'quantity_reserved' => $quantityReserved,
            'quantity_available' => $quantityOnHand - $quantityReserved,
            'item' => $item,
        ];
    }

    /** @return array{transaction: InventoryTransaction, balance: InventoryBalance} */
    public function stockIn(array $attributes, User $actor): array
    {
        return DB::transaction(fn (): array => $this->stockInInsideTransaction($attributes, $actor));
    }

    /** @return array{transaction: InventoryTransaction, balance: InventoryBalance} */
    public function stockOut(array $attributes, User $actor): array
    {
        return DB::transaction(fn (): array => $this->stockOutInsideTransaction($attributes, $actor));
    }

    /** @return array<int, array{transaction: InventoryTransaction, balance: InventoryBalance}> */
    public function postGoodsReceipt(GoodsReceipt $receipt, User $actor): array
    {
        return DB::transaction(function () use ($receipt, $actor): array {
            if ($receipt->status !== ProcurementWorkflow::ACCEPTED) {
                throw ValidationException::withMessages(['status' => 'Only accepted Goods Receipts can create accepted stock.']);
            }
            $receipt->loadMissing('items');
            $results = [];
            foreach ($receipt->items as $item) {
                if ((float) $item->accepted_quantity <= 0) {
                    continue;
                }
                $results[] = $this->stockInInsideTransaction([
                    'material_id' => $item->material_id,
                    'unit_id' => $item->unit_id,
                    'warehouse_id' => $receipt->warehouse_id,
                    'warehouse_location_id' => $receipt->warehouse_location_id,
                    'quantity' => $item->accepted_quantity,
                    'transaction_date' => now()->toDateTimeString(),
                    'reference_type' => GoodsReceiptItem::class,
                    'reference_id' => $item->getKey(),
                    'idempotency_key' => 'goods-receipt-item:'.$item->getKey().':accepted',
                    'remarks' => 'Accepted quantity from Goods Receipt '.$receipt->receipt_number.'.',
                ], $actor);
            }

            return $results;
        });
    }

    /** @return array{transaction: InventoryTransaction, balance: InventoryBalance} */
    private function stockInInsideTransaction(array $attributes, User $actor): array
    {
        $item = $this->referenceService->item($attributes);
        $warehouse = $this->referenceService->warehouse((int) $attributes['warehouse_id']);
        $location = $this->referenceService->location(isset($attributes['warehouse_location_id']) && $attributes['warehouse_location_id'] !== '' ? (int) $attributes['warehouse_location_id'] : null, $warehouse->getKey());
        $quantity = (float) ($attributes['quantity'] ?? 0);
        $this->assertPositiveQuantity($quantity);
        $idempotencyKey = $attributes['idempotency_key'] ?? null;
        if ($idempotencyKey !== null) {
            $existing = InventoryTransaction::query()->where('idempotency_key', $idempotencyKey)->first();
            if ($existing !== null) {
                return ['transaction' => $existing->load(['inventoryBalance', 'warehouse', 'warehouseLocation', 'material', 'product', 'productVariant', 'unit', 'performer']), 'balance' => $existing->inventoryBalance()->lockForUpdate()->firstOrFail()];
            }
        }
        $balance = $this->getOrCreateBalance($item, $warehouse->getKey(), $location?->getKey());
        $balance = InventoryBalance::query()->lockForUpdate()->findOrFail($balance->getKey());
        $balance->increment('quantity_on_hand', $quantity);
        $balance->refresh();
        $transaction = $this->createTransaction($balance, $item, $quantity, self::STOCK_IN, $actor, $attributes);
        $this->auditLogService->record($actor, 'inventory-transactions', $transaction, 'stock_in', null, $transaction->attributesToArray());

        return ['transaction' => $transaction->load(['inventoryBalance', 'warehouse', 'warehouseLocation', 'material', 'product', 'productVariant', 'unit', 'performer']), 'balance' => $balance->load(['warehouse', 'warehouseLocation', 'material', 'product', 'productVariant', 'unit'])];
    }

    /** @return array{transaction: InventoryTransaction, balance: InventoryBalance} */
    private function stockOutInsideTransaction(array $attributes, User $actor): array
    {
        $item = $this->referenceService->item($attributes);
        $warehouse = $this->referenceService->warehouse((int) $attributes['warehouse_id']);
        $location = $this->referenceService->location(isset($attributes['warehouse_location_id']) && $attributes['warehouse_location_id'] !== '' ? (int) $attributes['warehouse_location_id'] : null, $warehouse->getKey());
        $quantity = (float) ($attributes['quantity'] ?? 0);
        $this->assertPositiveQuantity($quantity);
        $idempotencyKey = $attributes['idempotency_key'] ?? null;
        if ($idempotencyKey !== null) {
            $existing = InventoryTransaction::query()->where('idempotency_key', $idempotencyKey)->first();
            if ($existing !== null) {
                return ['transaction' => $existing->load(['inventoryBalance', 'warehouse', 'warehouseLocation', 'material', 'product', 'productVariant', 'unit', 'performer']), 'balance' => $existing->inventoryBalance()->lockForUpdate()->firstOrFail()];
            }
        }
        $balance = $this->getOrCreateBalance($item, $warehouse->getKey(), $location?->getKey());
        $balance = InventoryBalance::query()->lockForUpdate()->findOrFail($balance->getKey());
        if ($balance->available_quantity < $quantity) {
            throw ValidationException::withMessages(['quantity' => 'Insufficient available stock for this stock-out.']);
        }
        $balance->decrement('quantity_on_hand', $quantity);
        $balance->refresh();
        $transaction = $this->createTransaction($balance, $item, $quantity, self::STOCK_OUT, $actor, $attributes);
        $this->auditLogService->record($actor, 'inventory-transactions', $transaction, 'stock_out', null, $transaction->attributesToArray());

        return ['transaction' => $transaction->load(['inventoryBalance', 'warehouse', 'warehouseLocation', 'material', 'product', 'productVariant', 'unit', 'performer']), 'balance' => $balance->load(['warehouse', 'warehouseLocation', 'material', 'product', 'productVariant', 'unit'])];
    }

    /** @param array<string, mixed> $item */
    public function getOrCreateBalance(array $item, int $warehouseId, ?int $locationId): InventoryBalance
    {
        $stockKey = $this->referenceService->stockKey($item, $warehouseId, $locationId);
        $balance = InventoryBalance::query()->where('stock_key', $stockKey)->first();
        if ($balance !== null) {
            return $balance;
        }

        return InventoryBalance::query()->create([
            'stock_key' => $stockKey,
            'warehouse_id' => $warehouseId,
            'warehouse_location_id' => $locationId,
            'material_id' => $item['material_id'],
            'product_id' => $item['product_id'],
            'product_variant_id' => $item['product_variant_id'],
            'unit_id' => $item['unit_id'],
            'quantity_on_hand' => 0,
            'quantity_reserved' => 0,
            'item_type' => $item['item_type'],
            'status' => 'active',
        ]);
    }

    /** @param array<string, mixed> $item */
    public function applyLockedMovement(InventoryBalance $balance, array $item, float $quantity, string $transactionType, User $actor, array $attributes): InventoryTransaction
    {
        $this->assertPositiveQuantity($quantity);
        if (in_array($transactionType, [self::STOCK_OUT, self::TRANSFER_OUT, self::ADJUSTMENT_OUT], true)) {
            if ((float) $balance->available_quantity < $quantity) {
                throw ValidationException::withMessages(['quantity' => 'Insufficient available stock for this movement.']);
            }
            $balance->decrement('quantity_on_hand', $quantity);
        } else {
            $balance->increment('quantity_on_hand', $quantity);
        }
        $balance->refresh();

        return $this->createTransaction($balance, $item, $quantity, $transactionType, $actor, $attributes);
    }

    private function createTransaction(InventoryBalance $balance, array $item, float $quantity, string $transactionType, User $actor, array $attributes): InventoryTransaction
    {
        $transaction = InventoryTransaction::query()->create([
            'transaction_number' => $this->generateTransactionNumber(),
            'inventory_balance_id' => $balance->getKey(),
            'warehouse_id' => $balance->warehouse_id,
            'warehouse_location_id' => $balance->warehouse_location_id,
            'material_id' => $item['material_id'],
            'product_id' => $item['product_id'],
            'product_variant_id' => $item['product_variant_id'],
            'unit_id' => $item['unit_id'],
            'quantity' => $quantity,
            'transaction_type' => $transactionType,
            'reference_type' => $attributes['reference_type'] ?? null,
            'reference_id' => $attributes['reference_id'] ?? null,
            'performed_by' => $actor->getKey(),
            'transaction_date' => $attributes['transaction_date'] ?? now(),
            'idempotency_key' => $attributes['idempotency_key'] ?? null,
            'remarks' => $attributes['remarks'] ?? null,
        ]);
        $this->auditLogService->record($actor, 'inventory-balances', $balance, 'quantity_changed', null, [
            'quantity_on_hand' => $balance->quantity_on_hand,
            'quantity_reserved' => $balance->quantity_reserved,
            'transaction_id' => $transaction->getKey(),
        ]);

        return $transaction;
    }

    /** @param Builder<InventoryBalance> $query */
    private function applyBalanceFilters(Builder $query, array $filters): void
    {
        if (($filters['search'] ?? null) !== null && $filters['search'] !== '') {
            $search = (string) $filters['search'];
            $query->where(function (Builder $builder) use ($search): void {
                $builder->where('stock_key', 'like', "%{$search}%")
                    ->orWhereHas('material', fn (Builder $q) => $q->where('code', 'like', "%{$search}%")->orWhere('name', 'like', "%{$search}%"))
                    ->orWhereHas('product', fn (Builder $q) => $q->where('code', 'like', "%{$search}%")->orWhere('name', 'like', "%{$search}%"))
                    ->orWhereHas('productVariant', fn (Builder $q) => $q->where('sku', 'like', "%{$search}%")->orWhere('variant_name', 'like', "%{$search}%"));
            });
        }
        foreach (['warehouse_id', 'warehouse_location_id', 'material_id', 'product_id', 'product_variant_id', 'item_type', 'status'] as $field) {
            if (($filters[$field] ?? null) !== null && $filters[$field] !== '') {
                $query->where($field, $filters[$field]);
            }
        }
    }

    private function assertPositiveQuantity(float $quantity): void
    {
        if ($quantity <= 0) {
            throw ValidationException::withMessages(['quantity' => 'Quantity must be greater than zero.']);
        }
    }

    private function generateTransactionNumber(): string
    {
        $prefix = 'INV-'.now()->format('Ymd');
        $sequence = InventoryTransaction::query()->where('transaction_number', 'like', "{$prefix}-%")->count() + 1;
        do {
            $candidate = sprintf('%s-%04d', $prefix, $sequence++);
        } while (InventoryTransaction::query()->where('transaction_number', $candidate)->exists());

        return $candidate;
    }
}
