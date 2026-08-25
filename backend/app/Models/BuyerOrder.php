<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable(['buyer_id', 'order_number', 'order_date', 'delivery_date', 'status', 'total_quantity', 'total_amount', 'remarks', 'created_by'])]
class BuyerOrder extends Model
{
    use SoftDeletes;

    protected function casts(): array
    {
        return [
            'order_date' => 'date:Y-m-d',
            'delivery_date' => 'date:Y-m-d',
            'total_quantity' => 'decimal:4',
            'total_amount' => 'decimal:4',
        ];
    }

    public function buyer(): BelongsTo
    {
        return $this->belongsTo(Buyer::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function items(): HasMany
    {
        return $this->hasMany(BuyerOrderItem::class)->orderBy('id');
    }

    public function approvals(): HasMany
    {
        return $this->hasMany(OrderApproval::class)->latest('id');
    }

    public function latestApproval(): HasOne
    {
        return $this->hasOne(OrderApproval::class)->latestOfMany();
    }

    public function statusHistories(): HasMany
    {
        return $this->hasMany(OrderStatusHistory::class)->orderBy('id');
    }

    public function planningInput(): HasOne
    {
        return $this->hasOne(OrderPlanningInput::class);
    }

    public function productionPlans(): HasMany
    {
        return $this->hasMany(ProductionPlan::class);
    }

    public function productionOrders(): HasMany
    {
        return $this->hasMany(ProductionOrder::class);
    }
}
