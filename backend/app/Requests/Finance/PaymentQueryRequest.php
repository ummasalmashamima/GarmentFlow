<?php

declare(strict_types=1);

namespace App\Requests\Finance;

use Illuminate\Foundation\Http\FormRequest;

class PaymentQueryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasPermission('finance.view') ?? false;
    }

    public function rules(): array
    {
        return FinanceRules::paymentQuery();
    }
}
