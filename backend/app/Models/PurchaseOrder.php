<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable([
    'purchase_order_number',
    'supplier_id',
    'po_date',
    'expected_delivery_date',
    'currency',
    'payment_terms',
    'shipping_terms',
    'subtotal',
    'tax_total',
    'discount_total',
    'total_amount',
    'status',
    'created_by',
    'remarks',
])]
class PurchaseOrder extends Model
{
    use SoftDeletes;

    protected function casts(): array
    {
        return [
            'po_date' => 'date:Y-m-d',
            'expected_delivery_date' => 'date:Y-m-d',
            'subtotal' => 'decimal:4',
            'tax_total' => 'decimal:4',
            'discount_total' => 'decimal:4',
            'total_amount' => 'decimal:4',
        ];
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function items(): HasMany
    {
        return $this->hasMany(PurchaseOrderItem::class)->orderBy('line_number');
    }

    public function approvals(): HasMany
    {
        return $this->hasMany(PurchaseApproval::class, 'document_id')->where('document_type', PurchaseApproval::ORDER);
    }

    public function goodsReceipts(): HasMany
    {
        return $this->hasMany(GoodsReceipt::class);
    }

    public function statusHistories(): HasMany
    {
        return $this->hasMany(ProcurementStatusHistory::class, 'document_id')->where('document_type', ProcurementStatusHistory::ORDER);
    }
}
