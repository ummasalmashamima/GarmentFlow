<?php

declare(strict_types=1);

namespace App\Requests\Production;

use Illuminate\Foundation\Http\FormRequest;

class MaterialConsumptionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasPermission('production.manage') ?? false;
    }

    /** @return array<string, array<int, string>> */
    public function rules(): array
    {
        return [
            'production_order_item_id' => ['required', 'integer', 'exists:production_order_items,id'],
            'quantity' => ['required', 'numeric', 'gt:0'],
            'consumption_date' => ['nullable', 'date'],
            'idempotency_key' => ['nullable', 'string', 'max:150'],
            'remarks' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
