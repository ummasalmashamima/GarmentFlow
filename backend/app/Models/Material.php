<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable(['material_category_id', 'unit_id', 'code', 'name', 'material_type', 'status', 'description'])]
class Material extends Model
{
    use SoftDeletes;

    public function materialCategory(): BelongsTo
    {
        return $this->belongsTo(MaterialCategory::class);
    }

    public function unit(): BelongsTo
    {
        return $this->belongsTo(Unit::class);
    }

    public function suppliers(): BelongsToMany
    {
        return $this->belongsToMany(Supplier::class, 'supplier_materials')
            ->withPivot(['supplier_sku', 'unit_price', 'currency', 'lead_time_days', 'minimum_order_quantity', 'is_preferred', 'status', 'notes'])
            ->withTimestamps();
    }

    public function bomItems(): HasMany
    {
        return $this->hasMany(BomItem::class);
    }

    public function materialRequirements(): HasMany
    {
        return $this->hasMany(MaterialRequirement::class);
    }

    public function materialRequirementSources(): HasMany
    {
        return $this->hasMany(MaterialRequirementSource::class);
    }
}
