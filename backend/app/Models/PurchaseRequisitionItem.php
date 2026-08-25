<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'purchase_requisition_id',
    'material_id',
    'unit_id',
    'material_requirement_id',
    'quantity',
    'converted_quantity',
    'remarks',
    'line_number',
])]
class PurchaseRequisitionItem extends Model
{
    protected function casts(): array
    {
        return [
            'quantity' => 'decimal:4',
            'converted_quantity' => 'decimal:4',
        ];
    }

    public function requisition(): BelongsTo
    {
        return $this->belongsTo(PurchaseRequisition::class, 'purchase_requisition_id');
    }

    public function material(): BelongsTo
    {
        return $this->belongsTo(Material::class);
    }

    public function unit(): BelongsTo
    {
        return $this->belongsTo(Unit::class);
    }

    public function materialRequirement(): BelongsTo
    {
        return $this->belongsTo(MaterialRequirement::class);
    }

    public function purchaseOrderItems(): HasMany
    {
        return $this->hasMany(PurchaseOrderItem::class);
    }

    public function remainingQuantity(): float
    {
        return max((float) $this->quantity - (float) $this->converted_quantity, 0.0);
    }
}
