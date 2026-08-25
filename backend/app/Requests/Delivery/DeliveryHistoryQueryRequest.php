<?php

declare(strict_types=1);

namespace App\Requests\Delivery;

use Illuminate\Foundation\Http\FormRequest;

class DeliveryHistoryQueryRequest extends FormRequest
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
            'action' => ['nullable', 'in:created,updated,dispatched,status_changed,tracking_updated'],
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date', 'after_or_equal:date_from'],
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
            'sort' => ['nullable', 'in:id,created_at,action,module'],
            'direction' => ['nullable', 'in:asc,desc'],
        ];
    }
}
