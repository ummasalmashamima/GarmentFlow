<?php

declare(strict_types=1);

namespace App\Requests\Procurement;

use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class GoodsReceiptRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() instanceof User && $this->user()->hasPermission('procurement.manage');
    }

    public function rules(): array
    {
        return [
            'purchase_order_id' => ['required', 'integer', 'exists:purchase_orders,id'],
            'supplier_id' => ['required', 'integer', Rule::exists('suppliers', 'id')->where(fn ($query) => $query->where('status', 'active'))],
            'warehouse_id' => ['required', 'integer', Rule::exists('warehouses', 'id')->where(fn ($query) => $query->where('status', 'active'))],
            'warehouse_location_id' => ['nullable', 'integer', 'exists:warehouse_locations,id'],
            'receipt_date' => ['required', 'date'],
            'remarks' => ['nullable', 'string', 'max:5000'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.purchase_order_item_id' => ['required', 'integer', 'exists:purchase_order_items,id'],
            'items.*.received_quantity' => ['required', 'numeric', 'gt:0'],
            'items.*.accepted_quantity' => ['required', 'numeric', 'min:0'],
            'items.*.rejected_quantity' => ['required', 'numeric', 'min:0'],
            'items.*.remarks' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
