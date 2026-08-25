<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable([
    'requisition_number',
    'request_date',
    'requested_by',
    'department_id',
    'source',
    'required_date',
    'priority',
    'status',
    'remarks',
])]
class PurchaseRequisition extends Model
{
    use SoftDeletes;

    protected function casts(): array
    {
        return [
            'request_date' => 'date:Y-m-d',
            'required_date' => 'date:Y-m-d',
        ];
    }

    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(PurchaseRequisitionItem::class)->orderBy('line_number');
    }

    public function approvals(): HasMany
    {
        return $this->hasMany(PurchaseApproval::class, 'document_id')->where('document_type', PurchaseApproval::REQUISITION);
    }

    public function statusHistories(): HasMany
    {
        return $this->hasMany(ProcurementStatusHistory::class, 'document_id')->where('document_type', ProcurementStatusHistory::REQUISITION);
    }
}
