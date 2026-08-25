<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'document_type',
    'document_id',
    'previous_status',
    'new_status',
    'changed_by',
    'remarks',
])]
class ProcurementStatusHistory extends Model
{
    public const REQUISITION = 'purchase_requisition';

    public const ORDER = 'purchase_order';

    public const RECEIPT = 'goods_receipt';

    public function changer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'changed_by');
    }
}
