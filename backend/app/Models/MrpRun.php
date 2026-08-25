<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'run_number',
    'status',
    'planning_date',
    'total_gross_quantity',
    'total_net_quantity',
    'inventory_data_available',
    'created_by',
    'calculated_at',
    'notes',
])]
class MrpRun extends Model
{
    protected function casts(): array
    {
        return [
            'planning_date' => 'date:Y-m-d',
            'calculated_at' => 'datetime',
            'total_gross_quantity' => 'decimal:4',
            'total_net_quantity' => 'decimal:4',
            'inventory_data_available' => 'boolean',
        ];
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function materialRequirements(): HasMany
    {
        return $this->hasMany(MaterialRequirement::class);
    }
}
