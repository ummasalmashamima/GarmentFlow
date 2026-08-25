<?php

declare(strict_types=1);

namespace App\Requests\Reporting;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class DashboardQueryRequest extends FormRequest
{
    public function authorize(): bool
    {
        $key = (string) $this->route('dashboard');

        return $this->user()?->hasPermission('dashboard.'.str_replace('-', '-', $key).'.view') ?? false;
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        return [
            'date_from' => ['nullable', 'date_format:Y-m-d'],
            'date_to' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:date_from'],
            'status' => ['nullable', 'string', 'max:40'],
            'supplier_id' => ['nullable', 'integer', Rule::exists('suppliers', 'id')],
            'product_id' => ['nullable', 'integer', Rule::exists('products', 'id')],
            'product_variant_id' => ['nullable', 'integer', Rule::exists('product_variants', 'id')],
            'buyer_id' => ['nullable', 'integer', Rule::exists('buyers', 'id')],
            'customer_id' => ['nullable', 'integer', Rule::exists('customers', 'id')],
            'warehouse_id' => ['nullable', 'integer', Rule::exists('warehouses', 'id')],
        ];
    }
}
