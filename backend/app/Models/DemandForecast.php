<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable([
    'product_id',
    'product_variant_id',
    'period_type',
    'period_start',
    'period_end',
    'forecast_quantity',
    'method',
    'status',
    'forecast_date',
    'confidence_score',
    'accuracy_score',
    'lookback_periods',
    'calculation_snapshot',
    'created_by',
    'notes',
])]
class DemandForecast extends Model
{
    use SoftDeletes;

    protected function casts(): array
    {
        return [
            'period_start' => 'date:Y-m-d',
            'period_end' => 'date:Y-m-d',
            'forecast_date' => 'date:Y-m-d',
            'forecast_quantity' => 'decimal:4',
            'confidence_score' => 'decimal:4',
            'accuracy_score' => 'decimal:4',
            'lookback_periods' => 'integer',
            'calculation_snapshot' => 'array',
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
}
