<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'product_id',
    'product_variant_id',
    'period_type',
    'period_start',
    'period_end',
    'confirmed_order_quantity',
    'forecast_quantity',
    'required_quantity',
    'available_quantity',
    'planned_production_quantity',
    'status',
    'created_by',
    'notes',
])]
class SupplyPlan extends Model
{
    protected function casts(): array
    {
        return [
            'period_start' => 'date:Y-m-d',
            'period_end' => 'date:Y-m-d',
            'confirmed_order_quantity' => 'decimal:4',
            'forecast_quantity' => 'decimal:4',
            'required_quantity' => 'decimal:4',
            'available_quantity' => 'decimal:4',
            'planned_production_quantity' => 'decimal:4',
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

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function materialRequirementSources(): HasMany
    {
        return $this->hasMany(MaterialRequirementSource::class);
    }

    public function productionPlans(): HasMany
    {
        return $this->hasMany(ProductionPlan::class);
    }
}
