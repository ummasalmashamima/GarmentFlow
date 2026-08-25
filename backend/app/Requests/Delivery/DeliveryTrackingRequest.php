<?php

declare(strict_types=1);

namespace App\Requests\Delivery;

use Illuminate\Foundation\Http\FormRequest;

class DeliveryTrackingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasPermission('delivery.manage') ?? false;
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        return [
            'carrier_name' => ['nullable', 'string', 'max:160'],
            'tracking_number' => ['nullable', 'string', 'max:160'],
            'location' => ['nullable', 'string', 'max:255'],
            'remarks' => ['nullable', 'string'],
        ];
    }
}
