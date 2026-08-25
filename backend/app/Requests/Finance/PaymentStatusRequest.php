<?php

declare(strict_types=1);

namespace App\Requests\Finance;

use Illuminate\Foundation\Http\FormRequest;

class PaymentStatusRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasPermission('finance.pay') ?? false;
    }

    public function rules(): array
    {
        return [
            'status' => ['required', 'in:voided'],
            'remarks' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
