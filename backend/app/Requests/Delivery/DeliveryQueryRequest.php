<?php

declare(strict_types=1);

namespace App\Requests\Delivery;

use App\Services\Delivery\DeliveryWorkflow;
use Illuminate\Foundation\Http\FormRequest;

class DeliveryQueryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasPermission('delivery.view') ?? false;
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        return [
            'search' => ['nullable', 'string', 'max:100'],
            'sales_order_id' => ['nullable', 'integer', 'exists:sales_orders,id'],
            'warehouse_id' => ['nullable', 'integer', 'exists:warehouses,id'],
            'status' => ['nullable', 'in:'.implode(',', app(DeliveryWorkflow::class)->statuses())],
            'delivery_date_from' => ['nullable', 'date'],
            'delivery_date_to' => ['nullable', 'date', 'after_or_equal:delivery_date_from'],
            'expected_delivery_date_from' => ['nullable', 'date'],
            'expected_delivery_date_to' => ['nullable', 'date', 'after_or_equal:expected_delivery_date_from'],
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
            'sort' => ['nullable', 'in:id,delivery_number,delivery_date,expected_delivery_date,status,ordered_quantity,dispatched_quantity,delivered_quantity'],
            'direction' => ['nullable', 'in:asc,desc'],
        ];
    }
}
