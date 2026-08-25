<?php

declare(strict_types=1);

namespace App\Requests\Reporting;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ReportQueryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasPermission('reports.view') ?? false;
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        return [
            'date_from' => ['nullable', 'date_format:Y-m-d'],
            'date_to' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:date_from'],
            'status' => ['nullable', 'string', 'max:40'],
            'buyer_id' => ['nullable', 'integer', Rule::exists('buyers', 'id')],
            'customer_id' => ['nullable', 'integer', Rule::exists('customers', 'id')],
            'supplier_id' => ['nullable', 'integer', Rule::exists('suppliers', 'id')],
            'product_id' => ['nullable', 'integer', Rule::exists('products', 'id')],
            'product_variant_id' => ['nullable', 'integer', Rule::exists('product_variants', 'id')],
            'category_id' => ['nullable', 'integer', Rule::exists('categories', 'id')],
            'warehouse_id' => ['nullable', 'integer', Rule::exists('warehouses', 'id')],
            'warehouse_location_id' => ['nullable', 'integer', Rule::exists('warehouse_locations', 'id')],
            'material_id' => ['nullable', 'integer', Rule::exists('materials', 'id')],
            'unit_id' => ['nullable', 'integer', Rule::exists('units', 'id')],
            'item_type' => ['nullable', 'string', 'max:30'],
            'transaction_type' => ['nullable', 'string', 'max:40'],
            'search' => ['nullable', 'string', 'max:100'],
            'sort' => ['nullable', 'string', 'max:60'],
            'direction' => ['nullable', Rule::in(['asc', 'desc'])],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
            'page' => ['nullable', 'integer', 'min:1'],
        ];
    }
}
