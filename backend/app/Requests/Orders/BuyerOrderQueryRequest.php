<?php

declare(strict_types=1);

namespace App\Requests\Orders;

use App\Services\Orders\BuyerOrderWorkflow;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class BuyerOrderQueryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasPermission('buyer-order.view') ?? false;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'search' => ['nullable', 'string', 'max:100'],
            'status' => ['nullable', 'string', Rule::in((new BuyerOrderWorkflow)->statuses())],
            'buyer_id' => ['nullable', 'integer', Rule::exists('buyers', 'id')],
            'order_date_from' => ['nullable', 'date_format:Y-m-d'],
            'order_date_to' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:order_date_from'],
            'delivery_date_from' => ['nullable', 'date_format:Y-m-d'],
            'delivery_date_to' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:delivery_date_from'],
            'sort' => ['nullable', 'string', Rule::in(['id', 'order_number', 'order_date', 'delivery_date', 'status', 'total_quantity', 'total_amount', 'created_at'])],
            'direction' => ['nullable', 'string', Rule::in(['asc', 'desc'])],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ];
    }
}
