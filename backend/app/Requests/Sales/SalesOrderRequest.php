<?php

declare(strict_types=1);

namespace App\Requests\Sales;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class SalesOrderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasPermission('sales.manage') ?? false;
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        return SalesOrderRules::headerRules();
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $partyCount = count(array_filter([
                $this->input('buyer_id'),
                $this->input('customer_id'),
            ], static fn ($value): bool => $value !== null && $value !== ''));

            if ($partyCount !== 1) {
                $validator->errors()->add('party', 'Provide exactly one active buyer or customer.');
            }
        });
    }
}
