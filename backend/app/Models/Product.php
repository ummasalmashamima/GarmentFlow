<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable(['category_id', 'unit_id', 'code', 'name', 'product_type', 'description', 'standard_cost', 'standard_price', 'status'])]
class Product extends Model
{
    use SoftDeletes;

    protected function casts(): array
    {
        return [
            'standard_cost' => 'decimal:4',
            'standard_price' => 'decimal:4',
        ];
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function unit(): BelongsTo
    {
        return $this->belongsTo(Unit::class);
    }

    public function variants(): HasMany
    {
        return $this->hasMany(ProductVariant::class);
    }

    public function boms(): HasMany
    {
        return $this->hasMany(BomHeader::class);
    }

    public function orderItems(): HasMany
    {
        return $this->hasMany(BuyerOrderItem::class);
    }

    public function salesOrderItems(): HasMany
    {
        return $this->hasMany(SalesOrderItem::class);
    }

    public function demandForecasts(): HasMany
    {
        return $this->hasMany(DemandForecast::class);
    }

    public function supplyPlans(): HasMany
    {
        return $this->hasMany(SupplyPlan::class);
    }

    public function materialRequirementSources(): HasMany
    {
        return $this->hasMany(MaterialRequirementSource::class);
    }

    public function productionPlans(): HasMany
    {
        return $this->hasMany(ProductionPlan::class);
    }

    public function productionOrders(): HasMany
    {
        return $this->hasMany(ProductionOrder::class);
    }

    public function finishedGoods(): HasMany
    {
        return $this->hasMany(FinishedGoods::class);
    }
}
