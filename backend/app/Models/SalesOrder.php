<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable([
    'sales_order_number',
    'buyer_id',
    'customer_id',
    'order_date',
    'required_delivery_date',
    'warehouse_id',
    'delivery_address',
    'contact_information',
    'status',
    'subtotal',
    'order_discount_amount',
    'order_tax_amount',
    'discount_amount',
    'tax_amount',
    'total_amount',
    'ordered_quantity',
    'confirmed_quantity',
    'delivered_quantity',
    'remaining_quantity',
    'confirmed_at',
    'remarks',
    'created_by',
])]
class SalesOrder extends Model
{
    use SoftDeletes;

    protected function casts(): array
    {
        return [
            'order_date' => 'date:Y-m-d',
            'required_delivery_date' => 'date:Y-m-d',
            'confirmed_at' => 'datetime',
            'subtotal' => 'decimal:4',
            'order_discount_amount' => 'decimal:4',
            'order_tax_amount' => 'decimal:4',
            'discount_amount' => 'decimal:4',
            'tax_amount' => 'decimal:4',
            'total_amount' => 'decimal:4',
            'ordered_quantity' => 'decimal:4',
            'confirmed_quantity' => 'decimal:4',
            'delivered_quantity' => 'decimal:4',
            'remaining_quantity' => 'decimal:4',
        ];
    }

    public function buyer(): BelongsTo
    {
        return $this->belongsTo(Buyer::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function items(): HasMany
    {
        return $this->hasMany(SalesOrderItem::class)->orderBy('line_number');
    }

    public function statusHistories(): HasMany
    {
        return $this->hasMany(SalesOrderStatusHistory::class)->orderBy('id');
    }

    public function deliveries(): HasMany
    {
        return $this->hasMany(Delivery::class)->orderBy('id');
    }

    public function invoices(): HasMany
    {
        return $this->hasMany(Invoice::class);
    }
}
