<?php

declare(strict_types=1);

namespace App\Requests\Sales;

use App\Services\Sales\SalesOrderWorkflow;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SalesOrderQueryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasPermission('sales.view') ?? false;
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        return [
            'search' => ['nullable', 'string', 'max:100'],
            'status' => ['nullable', 'string', Rule::in((new SalesOrderWorkflow)->statuses())],
            'buyer_id' => ['nullable', 'integer', Rule::exists('buyers', 'id')],
            'customer_id' => ['nullable', 'integer', Rule::exists('customers', 'id')],
            'warehouse_id' => ['nullable', 'integer', Rule::exists('warehouses', 'id')],
            'order_date_from' => ['nullable', 'date_format:Y-m-d'],
            'order_date_to' => ['nullable', 'date_format:Y-m-d'],
            'required_delivery_date_from' => ['nullable', 'date_format:Y-m-d'],
            'required_delivery_date_to' => ['nullable', 'date_format:Y-m-d'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
            'sort' => ['nullable', 'string', Rule::in(['id', 'sales_order_number', 'order_date', 'required_delivery_date', 'ordered_quantity', 'total_amount', 'status'])],
            'direction' => ['nullable', 'string', Rule::in(['asc', 'desc'])],
        ];
    }
}
