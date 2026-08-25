<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

#[Fillable([
    'order_number',
    'production_plan_id',
    'buyer_order_id',
    'product_id',
    'product_variant_id',
    'bom_version_id',
    'planned_quantity',
    'completed_quantity',
    'rejected_quantity',
    'start_date',
    'expected_completion_date',
    'completed_date',
    'issue_warehouse_id',
    'issue_warehouse_location_id',
    'status',
    'created_by',
    'completed_by',
    'remarks',
])]
class ProductionOrder extends Model
{
    protected function casts(): array
    {
        return [
            'planned_quantity' => 'decimal:4',
            'completed_quantity' => 'decimal:4',
            'rejected_quantity' => 'decimal:4',
            'start_date' => 'date:Y-m-d',
            'expected_completion_date' => 'date:Y-m-d',
            'completed_date' => 'date:Y-m-d',
        ];
    }

    public function productionPlan(): BelongsTo
    {
        return $this->belongsTo(ProductionPlan::class);
    }

    public function buyerOrder(): BelongsTo
    {
        return $this->belongsTo(BuyerOrder::class);
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

    public function issueWarehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class, 'issue_warehouse_id');
    }

    public function issueWarehouseLocation(): BelongsTo
    {
        return $this->belongsTo(WarehouseLocation::class, 'issue_warehouse_location_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function completer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'completed_by');
    }

    public function items(): HasMany
    {
        return $this->hasMany(ProductionOrderItem::class)->orderBy('id');
    }

    public function progress(): HasMany
    {
        return $this->hasMany(ProductionProgress::class)->latest('id');
    }

    public function consumptions(): HasMany
    {
        return $this->hasMany(MaterialConsumption::class)->latest('id');
    }

    public function finishedGoods(): HasOne
    {
        return $this->hasOne(FinishedGoods::class);
    }
}
