<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'plan_number',
    'product_id',
    'product_variant_id',
    'supply_plan_id',
    'buyer_order_id',
    'planned_quantity',
    'planned_start_date',
    'planned_end_date',
    'priority',
    'status',
    'created_by',
    'remarks',
])]
class ProductionPlan extends Model
{
    protected function casts(): array
    {
        return [
            'planned_start_date' => 'date:Y-m-d',
            'planned_end_date' => 'date:Y-m-d',
            'planned_quantity' => 'decimal:4',
        ];
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function productVariant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class);
    }

    public function supplyPlan(): BelongsTo
    {
        return $this->belongsTo(SupplyPlan::class);
    }

    public function buyerOrder(): BelongsTo
    {
        return $this->belongsTo(BuyerOrder::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function productionOrders(): HasMany
    {
        return $this->hasMany(ProductionOrder::class);
    }
}
