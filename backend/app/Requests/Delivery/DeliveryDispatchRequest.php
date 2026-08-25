<?php

declare(strict_types=1);

namespace App\Requests\Delivery;

use Illuminate\Foundation\Http\FormRequest;

class DeliveryDispatchRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasPermission('delivery.dispatch') ?? false;
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        return DeliveryRules::remarks();
    }
}
