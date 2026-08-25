<?php

declare(strict_types=1);

namespace App\Requests\Sales;

use Illuminate\Foundation\Http\FormRequest;

class SalesOrderPreviewRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasPermission('sales.view') ?? false;
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        $rules = [
            'items' => ['required', 'array', 'min:1'],
            'order_discount_amount' => ['nullable', 'numeric', 'min:0'],
            'order_tax_amount' => ['nullable', 'numeric', 'min:0'],
        ];
        foreach (SalesOrderRules::itemRules() as $field => $fieldRules) {
            $rules["items.*.{$field}"] = $fieldRules;
        }

        return $rules;
    }
}
