<?php

declare(strict_types=1);

namespace App\Requests\Orders;

use App\Services\Orders\BuyerOrderWorkflow;
use Illuminate\Validation\Rule;

class BuyerOrderTransitionRequest extends BuyerOrderActionRequest
{
    protected function requiredPermission(): string
    {
        return 'buyer-order.manage';
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'status' => ['required', 'string', Rule::in((new BuyerOrderWorkflow)->statuses())],
            'remarks' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
