<?php

declare(strict_types=1);

namespace App\Requests\Procurement;

use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class PurchaseOrderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() instanceof User && $this->user()->hasPermission('procurement.manage');
    }

    public function rules(): array
    {
        return [
            'purchase_requisition_id' => ['required', 'integer', 'exists:purchase_requisitions,id'],
            'supplier_id' => ['required', 'integer', Rule::exists('suppliers', 'id')->where(fn ($query) => $query->where('status', 'active'))],
            'po_date' => ['required', 'date'],
            'expected_delivery_date' => ['required', 'date', 'after_or_equal:po_date'],
            'currency' => ['required', 'string', 'max:10'],
            'payment_terms' => ['nullable', 'string', 'max:120'],
            'shipping_terms' => ['nullable', 'string', 'max:120'],
            'tax_total' => ['nullable', 'numeric', 'min:0'],
            'discount_total' => ['nullable', 'numeric', 'min:0'],
            'remarks' => ['nullable', 'string', 'max:5000'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.purchase_requisition_item_id' => ['required', 'integer', 'exists:purchase_requisition_items,id'],
            'items.*.quantity' => ['required', 'numeric', 'gt:0'],
            'items.*.unit_price' => ['required', 'numeric', 'min:0'],
            'items.*.remarks' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
