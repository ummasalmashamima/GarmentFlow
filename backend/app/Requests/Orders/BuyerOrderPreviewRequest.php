<?php

declare(strict_types=1);

namespace App\Requests\Orders;

use Illuminate\Foundation\Http\FormRequest;

class BuyerOrderPreviewRequest extends FormRequest
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
            'items' => ['required', 'array', 'min:1'],
            ...array_combine(
                array_map(static fn (string $field): string => "items.*.{$field}", array_keys(BuyerOrderRules::itemRules())),
                array_values(BuyerOrderRules::itemRules()),
            ),
        ];
    }
}
