<?php

declare(strict_types=1);

namespace App\Requests\Orders;

use Illuminate\Foundation\Http\FormRequest;

class BuyerOrderItemRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasPermission('buyer-order.manage') ?? false;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return BuyerOrderRules::itemRules();
    }
}
