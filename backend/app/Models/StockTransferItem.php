<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'stock_transfer_id',
    'source_inventory_balance_id',
    'destination_inventory_balance_id',
    'material_id',
    'product_id',
    'product_variant_id',
    'unit_id',
    'quantity',
    'line_number',
    'remarks',
])]
class StockTransferItem extends Model
{
    protected function casts(): array
    {
        return ['quantity' => 'decimal:4'];
    }

    public function transfer(): BelongsTo
    {
        return $this->belongsTo(StockTransfer::class, 'stock_transfer_id');
    }

    public function sourceBalance(): BelongsTo
    {
        return $this->belongsTo(InventoryBalance::class, 'source_inventory_balance_id');
    }

    public function destinationBalance(): BelongsTo
    {
        return $this->belongsTo(InventoryBalance::class, 'destination_inventory_balance_id');
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
