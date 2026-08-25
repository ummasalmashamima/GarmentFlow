<?php

declare(strict_types=1);

namespace App\Requests\Finance;

use Illuminate\Foundation\Http\FormRequest;

class PaymentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasPermission('finance.pay') ?? false;
    }

    public function rules(): array
    {
        return FinanceRules::paymentCreate();
    }
}
