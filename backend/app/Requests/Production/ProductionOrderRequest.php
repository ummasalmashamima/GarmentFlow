<?php

declare(strict_types=1);

namespace App\Requests\Production;

use Illuminate\Foundation\Http\FormRequest;

class ProductionOrderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasPermission('production.manage') ?? false;
    }

    /** @return array<string, array<int, string>> */
    public function rules(): array
    {
        return [
            'production_plan_id' => ['required', 'integer', 'exists:production_plans,id'],
            'planned_quantity' => ['nullable', 'numeric', 'gt:0'],
            'expected_completion_date' => ['nullable', 'date'],
            'issue_warehouse_id' => ['required', 'integer', 'exists:warehouses,id'],
            'issue_warehouse_location_id' => ['nullable', 'integer', 'exists:warehouse_locations,id'],
            'remarks' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
