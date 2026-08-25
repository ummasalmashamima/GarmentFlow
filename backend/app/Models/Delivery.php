<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable([
    'delivery_number',
    'sales_order_id',
    'warehouse_id',
    'status',
    'delivery_date',
    'expected_delivery_date',
    'dispatched_at',
    'delivered_at',
    'ordered_quantity',
    'dispatched_quantity',
    'delivered_quantity',
    'remaining_quantity',
    'carrier_name',
    'tracking_number',
    'delivery_address',
    'contact_information',
    'remarks',
    'created_by',
])]
class Delivery extends Model
{
    use SoftDeletes;

    protected function casts(): array
    {
        return [
            'delivery_date' => 'date:Y-m-d',
            'expected_delivery_date' => 'date:Y-m-d',
            'dispatched_at' => 'datetime',
            'delivered_at' => 'datetime',
            'ordered_quantity' => 'decimal:4',
            'dispatched_quantity' => 'decimal:4',
            'delivered_quantity' => 'decimal:4',
            'remaining_quantity' => 'decimal:4',
        ];
    }

    public function salesOrder(): BelongsTo
    {
        return $this->belongsTo(SalesOrder::class);
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
        return $this->hasMany(DeliveryItem::class)->orderBy('line_number');
    }

    public function trackingHistories(): HasMany
    {
        return $this->hasMany(DeliveryTrackingHistory::class)->orderBy('id');
    }
}
