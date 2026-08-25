<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'sales_order_id',
    'line_number',
    'product_id',
    'product_variant_id',
    'unit_id',
    'ordered_quantity',
    'confirmed_quantity',
    'delivered_quantity',
    'remaining_quantity',
    'unit_price',
    'discount_amount',
    'tax_amount',
    'line_total',
    'remarks',
])]
class SalesOrderItem extends Model
{
    protected function casts(): array
    {
        return [
            'ordered_quantity' => 'decimal:4',
            'confirmed_quantity' => 'decimal:4',
            'delivered_quantity' => 'decimal:4',
            'remaining_quantity' => 'decimal:4',
            'unit_price' => 'decimal:4',
            'discount_amount' => 'decimal:4',
            'tax_amount' => 'decimal:4',
            'line_total' => 'decimal:4',
        ];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(SalesOrder::class, 'sales_order_id');
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

    public function deliveryItems(): HasMany
    {
        return $this->hasMany(DeliveryItem::class)->orderBy('id');
    }
}
