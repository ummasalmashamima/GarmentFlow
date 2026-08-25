<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'production_order_id',
    'planned_quantity',
    'completed_quantity',
    'rejected_quantity',
    'remaining_quantity',
    'progress_percentage',
    'production_date',
    'recorded_by',
    'remarks',
])]
class ProductionProgress extends Model
{
    protected function casts(): array
    {
        return [
            'planned_quantity' => 'decimal:4',
            'completed_quantity' => 'decimal:4',
            'rejected_quantity' => 'decimal:4',
            'remaining_quantity' => 'decimal:4',
            'progress_percentage' => 'decimal:4',
            'production_date' => 'date:Y-m-d',
        ];
    }

    public function productionOrder(): BelongsTo
    {
        return $this->belongsTo(ProductionOrder::class);
    }

    public function recorder(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }
}
