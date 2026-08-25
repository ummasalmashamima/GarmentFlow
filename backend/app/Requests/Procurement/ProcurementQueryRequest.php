<?php

declare(strict_types=1);

namespace App\Requests\Procurement;

use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ProcurementQueryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() instanceof User && $this->user()->hasPermission('procurement.view');
    }

    public function rules(): array
    {
        return [
            'search' => ['nullable', 'string', 'max:120'],
            'status' => ['nullable', 'string', 'max:30'],
            'priority' => ['nullable', 'string', 'max:20'],
            'supplier_id' => ['nullable', 'integer', Rule::exists('suppliers', 'id')->where(fn ($query) => $query->where('status', 'active'))],
            'warehouse_id' => ['nullable', 'integer', Rule::exists('warehouses', 'id')->where(fn ($query) => $query->where('status', 'active'))],
            'purchase_order_id' => ['nullable', 'integer', 'exists:purchase_orders,id'],
            'department_id' => ['nullable', 'integer', 'exists:departments,id'],
            'currency' => ['nullable', 'string', 'max:10'],
            'document_type' => ['nullable', 'string', 'max:30'],
            'new_status' => ['nullable', 'string', 'max:30'],
            'changed_by' => ['nullable', 'integer', 'exists:users,id'],
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date', 'after_or_equal:date_from'],
            'request_date_from' => ['nullable', 'date'],
            'request_date_to' => ['nullable', 'date', 'after_or_equal:request_date_from'],
            'required_date_from' => ['nullable', 'date'],
            'required_date_to' => ['nullable', 'date', 'after_or_equal:required_date_from'],
            'po_date_from' => ['nullable', 'date'],
            'po_date_to' => ['nullable', 'date', 'after_or_equal:po_date_from'],
            'expected_delivery_from' => ['nullable', 'date'],
            'expected_delivery_to' => ['nullable', 'date', 'after_or_equal:expected_delivery_from'],
            'receipt_date_from' => ['nullable', 'date'],
            'receipt_date_to' => ['nullable', 'date', 'after_or_equal:receipt_date_from'],
            'sort' => ['nullable', 'string', 'max:40'],
            'direction' => ['nullable', Rule::in(['asc', 'desc'])],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ];
    }
}
