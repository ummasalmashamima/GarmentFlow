<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'consumption_number',
    'production_order_id',
    'production_order_item_id',
    'material_id',
    'unit_id',
    'quantity',
    'inventory_transaction_id',
    'idempotency_key',
    'consumption_date',
    'recorded_by',
    'remarks',
])]
class MaterialConsumption extends Model
{
    protected function casts(): array
    {
        return [
            'quantity' => 'decimal:4',
            'consumption_date' => 'date:Y-m-d',
        ];
    }

    public function productionOrder(): BelongsTo
    {
        return $this->belongsTo(ProductionOrder::class);
    }

    public function productionOrderItem(): BelongsTo
    {
        return $this->belongsTo(ProductionOrderItem::class);
    }

    public function material(): BelongsTo
    {
        return $this->belongsTo(Material::class);
    }

    public function unit(): BelongsTo
    {
        return $this->belongsTo(Unit::class);
    }

    public function inventoryTransaction(): BelongsTo
    {
        return $this->belongsTo(InventoryTransaction::class);
    }

    public function recorder(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }
}
