<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable(['bom_header_id', 'version_number', 'effective_from', 'effective_to', 'status', 'notes'])]
class BomVersion extends Model
{
    use SoftDeletes;

    protected function casts(): array
    {
        return [
            'version_number' => 'integer',
            'effective_from' => 'date:Y-m-d',
            'effective_to' => 'date:Y-m-d',
        ];
    }

    public function bomHeader(): BelongsTo
    {
        return $this->belongsTo(BomHeader::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(BomItem::class)->orderBy('line_number');
    }

    public function materialRequirementSources(): HasMany
    {
        return $this->hasMany(MaterialRequirementSource::class);
    }

    public function productionOrders(): HasMany
    {
        return $this->hasMany(ProductionOrder::class);
    }
}
