<?php

declare(strict_types=1);

namespace App\Requests\Production;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class ProductionPlanRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasPermission('production.manage') ?? false;
    }

    /** @return array<string, array<int, string>> */
    public function rules(): array
    {
        return [
            'product_id' => ['required', 'integer', 'exists:products,id'],
            'product_variant_id' => ['nullable', 'integer', 'exists:product_variants,id'],
            'supply_plan_id' => ['nullable', 'integer', 'exists:supply_plans,id'],
            'buyer_order_id' => ['nullable', 'integer', 'exists:buyer_orders,id'],
            'planned_quantity' => ['required', 'numeric', 'gt:0'],
            'planned_start_date' => ['required', 'date'],
            'planned_end_date' => ['required', 'date', 'after_or_equal:planned_start_date'],
            'priority' => ['nullable', 'in:low,normal,high,urgent'],
            'remarks' => ['nullable', 'string', 'max:2000'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if (! $this->filled('supply_plan_id') && ! $this->filled('buyer_order_id')) {
                $validator->errors()->add('source', 'Provide a Supply Plan or Buyer Order as the production source.');
            }
        });
    }
}
