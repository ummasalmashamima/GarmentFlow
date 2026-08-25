<?php

declare(strict_types=1);

namespace App\Requests\Inventory;

use Illuminate\Foundation\Http\FormRequest;

class InventoryQueryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasPermission('inventory.view') ?? false;
    }

    public function rules(): array
    {
        return [
            'search' => ['nullable', 'string', 'max:100'],
            'warehouse_id' => ['nullable', 'integer', 'exists:warehouses,id'],
            'warehouse_location_id' => ['nullable', 'integer', 'exists:warehouse_locations,id'],
            'material_id' => ['nullable', 'integer', 'exists:materials,id'],
            'product_id' => ['nullable', 'integer', 'exists:products,id'],
            'product_variant_id' => ['nullable', 'integer', 'exists:product_variants,id'],
            'item_type' => ['nullable', 'in:material,product,product_variant'],
            'transaction_type' => ['nullable', 'in:STOCK_IN,STOCK_OUT,TRANSFER_IN,TRANSFER_OUT,ADJUSTMENT_IN,ADJUSTMENT_OUT'],
            'performed_by' => ['nullable', 'integer', 'exists:users,id'],
            'source_warehouse_id' => ['nullable', 'integer', 'exists:warehouses,id'],
            'destination_warehouse_id' => ['nullable', 'integer', 'exists:warehouses,id'],
            'adjustment_direction' => ['nullable', 'in:IN,OUT'],
            'status' => ['nullable', 'string', 'max:30'],
            'transaction_date_from' => ['nullable', 'date'],
            'transaction_date_to' => ['nullable', 'date', 'after_or_equal:transaction_date_from'],
            'transfer_date_from' => ['nullable', 'date'],
            'transfer_date_to' => ['nullable', 'date', 'after_or_equal:transfer_date_from'],
            'adjustment_date_from' => ['nullable', 'date'],
            'adjustment_date_to' => ['nullable', 'date', 'after_or_equal:adjustment_date_from'],
            'sort' => ['nullable', 'string', 'max:40'],
            'direction_sort' => ['nullable', 'in:asc,desc'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
            'page' => ['nullable', 'integer', 'min:1'],
        ];
    }
}
