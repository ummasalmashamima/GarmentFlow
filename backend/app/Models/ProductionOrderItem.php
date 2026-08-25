<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'production_order_id',
    'bom_item_id',
    'material_id',
    'unit_id',
    'bom_quantity',
    'wastage_percentage',
    'required_quantity',
    'consumed_quantity',
    'remarks',
])]
class ProductionOrderItem extends Model
{
    protected function casts(): array
    {
        return [
            'bom_quantity' => 'decimal:4',
            'wastage_percentage' => 'decimal:4',
            'required_quantity' => 'decimal:4',
            'consumed_quantity' => 'decimal:4',
        ];
    }

    public function productionOrder(): BelongsTo
    {
        return $this->belongsTo(ProductionOrder::class);
    }

    public function bomItem(): BelongsTo
    {
        return $this->belongsTo(BomItem::class);
    }

    public function material(): BelongsTo
    {
        return $this->belongsTo(Material::class);
    }

    public function unit(): BelongsTo
    {
        return $this->belongsTo(Unit::class);
    }

    public function consumptions(): HasMany
    {
        return $this->hasMany(MaterialConsumption::class);
    }

    public function getRemainingQuantityAttribute(): float
    {
        return max((float) $this->required_quantity - (float) $this->consumed_quantity, 0);
    }
}
