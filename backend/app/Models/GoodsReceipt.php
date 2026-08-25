<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'receipt_number',
    'purchase_order_id',
    'supplier_id',
    'warehouse_id',
    'warehouse_location_id',
    'receipt_date',
    'received_by',
    'status',
    'remarks',
    'posted_at',
])]
class GoodsReceipt extends Model
{
    protected function casts(): array
    {
        return [
            'receipt_date' => 'date:Y-m-d',
            'posted_at' => 'datetime',
        ];
    }

    public function purchaseOrder(): BelongsTo
    {
        return $this->belongsTo(PurchaseOrder::class);
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function warehouseLocation(): BelongsTo
    {
        return $this->belongsTo(WarehouseLocation::class);
    }

    public function receiver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'received_by');
    }

    public function items(): HasMany
    {
        return $this->hasMany(GoodsReceiptItem::class)->orderBy('line_number');
    }

    public function statusHistories(): HasMany
    {
        return $this->hasMany(ProcurementStatusHistory::class, 'document_id')->where('document_type', ProcurementStatusHistory::RECEIPT);
    }
}
