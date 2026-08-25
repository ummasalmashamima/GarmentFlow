<?php

declare(strict_types=1);

namespace App\Requests\Sales;

use Illuminate\Foundation\Http\FormRequest;

class SalesOrderConfirmRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasPermission('sales.confirm') ?? false;
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        return ['remarks' => ['nullable', 'string', 'max:2000']];
    }
}
