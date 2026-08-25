<?php

declare(strict_types=1);

namespace App\Requests\Delivery;

use App\Services\Delivery\DeliveryWorkflow;
use Illuminate\Foundation\Http\FormRequest;

class DeliveryStatusRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasPermission('delivery.manage') ?? false;
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        return [
            'status' => ['required', 'in:'.implode(',', app(DeliveryWorkflow::class)->statuses())],
            'remarks' => ['nullable', 'string'],
        ];
    }
}
