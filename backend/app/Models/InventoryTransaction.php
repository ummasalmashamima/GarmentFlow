<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

#[Fillable([
    'transaction_number',
    'inventory_balance_id',
    'warehouse_id',
    'warehouse_location_id',
    'material_id',
    'product_id',
    'product_variant_id',
    'unit_id',
    'quantity',
    'transaction_type',
    'reference_type',
    'reference_id',
    'performed_by',
    'transaction_date',
    'idempotency_key',
    'remarks',
])]
class InventoryTransaction extends Model
{
    protected function casts(): array
    {
        return [
            'quantity' => 'decimal:4',
            'transaction_date' => 'datetime',
        ];
    }

    public function inventoryBalance(): BelongsTo
    {
        return $this->belongsTo(InventoryBalance::class);
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function warehouseLocation(): BelongsTo
    {
        return $this->belongsTo(WarehouseLocation::class);
    }

    public function material(): BelongsTo
    {
        return $this->belongsTo(Material::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function productVariant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class);
    }

    public function unit(): BelongsTo
    {
        return $this->belongsTo(Unit::class);
    }

    public function performer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'performed_by');
    }

    public function materialConsumption(): HasOne
    {
        return $this->hasOne(MaterialConsumption::class);
    }

    public function finishedGoods(): HasOne
    {
        return $this->hasOne(FinishedGoods::class);
    }
}
