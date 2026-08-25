<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'document_type',
    'document_id',
    'requested_by',
    'reviewed_by',
    'status',
    'remarks',
    'requested_at',
    'reviewed_at',
])]
class PurchaseApproval extends Model
{
    public const REQUISITION = 'purchase_requisition';

    public const ORDER = 'purchase_order';

    protected function casts(): array
    {
        return [
            'requested_at' => 'datetime',
            'reviewed_at' => 'datetime',
        ];
    }

    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }
}
