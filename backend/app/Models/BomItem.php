<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['bom_version_id', 'material_id', 'unit_id', 'quantity', 'wastage_percentage', 'line_number', 'notes'])]
class BomItem extends Model
{
    protected function casts(): array
    {
        return [
            'quantity' => 'decimal:4',
            'wastage_percentage' => 'decimal:4',
            'line_number' => 'integer',
        ];
    }

    public function bomVersion(): BelongsTo
    {
        return $this->belongsTo(BomVersion::class);
    }

    public function material(): BelongsTo
    {
        return $this->belongsTo(Material::class);
    }

    public function unit(): BelongsTo
    {
        return $this->belongsTo(Unit::class);
    }

    public function materialRequirementSources(): HasMany
    {
        return $this->hasMany(MaterialRequirementSource::class);
    }
}
