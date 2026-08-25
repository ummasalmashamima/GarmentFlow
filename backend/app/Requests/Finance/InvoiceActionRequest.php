<?php

declare(strict_types=1);

namespace App\Requests\Finance;

use Illuminate\Foundation\Http\FormRequest;

class InvoiceActionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasPermission('finance.manage') ?? false;
    }

    public function rules(): array
    {
        return [
            'remarks' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
