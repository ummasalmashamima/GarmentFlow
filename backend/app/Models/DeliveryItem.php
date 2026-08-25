<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'delivery_id',
    'sales_order_item_id',
    'line_number',
    'product_id',
    'product_variant_id',
    'unit_id',
    'delivery_quantity',
    'dispatched_quantity',
    'delivered_quantity',
    'remaining_quantity',
    'inventory_transaction_id',
    'remarks',
])]
class DeliveryItem extends Model
{
    protected function casts(): array
    {
        return [
            'delivery_quantity' => 'decimal:4',
            'dispatched_quantity' => 'decimal:4',
            'delivered_quantity' => 'decimal:4',
            'remaining_quantity' => 'decimal:4',
        ];
    }

    public function delivery(): BelongsTo
    {
        return $this->belongsTo(Delivery::class);
    }

    public function salesOrderItem(): BelongsTo
    {
        return $this->belongsTo(SalesOrderItem::class);
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

    public function inventoryTransaction(): BelongsTo
    {
        return $this->belongsTo(InventoryTransaction::class);
    }
}
