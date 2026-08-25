<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'stock_adjustment_id',
    'inventory_balance_id',
    'material_id',
    'product_id',
    'product_variant_id',
    'unit_id',
    'quantity',
    'line_number',
    'remarks',
])]
class StockAdjustmentItem extends Model
{
    protected function casts(): array
    {
        return ['quantity' => 'decimal:4'];
    }

    public function adjustment(): BelongsTo
    {
        return $this->belongsTo(StockAdjustment::class, 'stock_adjustment_id');
    }

    public function inventoryBalance(): BelongsTo
    {
        return $this->belongsTo(InventoryBalance::class);
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
}
