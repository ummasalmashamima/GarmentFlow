<?php

declare(strict_types=1);

namespace App\Requests\Sales;

use App\Services\Sales\SalesOrderWorkflow;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SalesOrderStatusRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasPermission('sales.manage') ?? false;
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        return [
            'status' => ['required', 'string', Rule::in((new SalesOrderWorkflow)->statuses())],
            'remarks' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
