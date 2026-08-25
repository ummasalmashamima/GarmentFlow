<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'mrp_run_id',
    'material_id',
    'unit_id',
    'gross_quantity',
    'available_quantity',
    'allocated_quantity',
    'net_quantity',
    'status',
    'notes',
])]
class MaterialRequirement extends Model
{
    protected function casts(): array
    {
        return [
            'gross_quantity' => 'decimal:4',
            'available_quantity' => 'decimal:4',
            'allocated_quantity' => 'decimal:4',
            'net_quantity' => 'decimal:4',
        ];
    }

    public function mrpRun(): BelongsTo
    {
        return $this->belongsTo(MrpRun::class);
    }

    public function material(): BelongsTo
    {
        return $this->belongsTo(Material::class);
    }

    public function unit(): BelongsTo
    {
        return $this->belongsTo(Unit::class);
    }

    public function sources(): HasMany
    {
        return $this->hasMany(MaterialRequirementSource::class);
    }
}
