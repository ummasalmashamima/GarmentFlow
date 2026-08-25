<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'material_requirement_id',
    'supply_plan_id',
    'product_id',
    'product_variant_id',
    'bom_version_id',
    'bom_item_id',
    'material_id',
    'unit_id',
    'planned_product_quantity',
    'bom_quantity',
    'wastage_percentage',
    'gross_quantity',
])]
class MaterialRequirementSource extends Model
{
    protected function casts(): array
    {
        return [
            'planned_product_quantity' => 'decimal:4',
            'bom_quantity' => 'decimal:4',
            'wastage_percentage' => 'decimal:4',
            'gross_quantity' => 'decimal:4',
        ];
    }

    public function materialRequirement(): BelongsTo
    {
        return $this->belongsTo(MaterialRequirement::class);
    }

    public function supplyPlan(): BelongsTo
    {
        return $this->belongsTo(SupplyPlan::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function productVariant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class);
    }

    public function bomVersion(): BelongsTo
    {
        return $this->belongsTo(BomVersion::class);
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
}
