<?php

declare(strict_types=1);

namespace App\Requests\Production;

use Illuminate\Foundation\Http\FormRequest;

class ProductionQueryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasPermission('production.view') ?? false;
    }

    /** @return array<string, array<int, string>> */
    public function rules(): array
    {
        return [
            'search' => ['nullable', 'string', 'max:100'],
            'status' => ['nullable', 'string', 'max:30'],
            'priority' => ['nullable', 'string', 'max:20'],
            'module' => ['nullable', 'string', 'max:60'],
            'action' => ['nullable', 'string', 'max:60'],
            'product_id' => ['nullable', 'integer', 'exists:products,id'],
            'product_variant_id' => ['nullable', 'integer', 'exists:product_variants,id'],
            'production_plan_id' => ['nullable', 'integer', 'exists:production_plans,id'],
            'production_order_id' => ['nullable', 'integer', 'exists:production_orders,id'],
            'supply_plan_id' => ['nullable', 'integer', 'exists:supply_plans,id'],
            'buyer_order_id' => ['nullable', 'integer', 'exists:buyer_orders,id'],
            'issue_warehouse_id' => ['nullable', 'integer', 'exists:warehouses,id'],
            'issue_warehouse_location_id' => ['nullable', 'integer', 'exists:warehouse_locations,id'],
            'material_id' => ['nullable', 'integer', 'exists:materials,id'],
            'recorded_by' => ['nullable', 'integer', 'exists:users,id'],
            'user_id' => ['nullable', 'integer', 'exists:users,id'],
            'record_id' => ['nullable', 'integer', 'min:1'],
            'planned_start_from' => ['nullable', 'date'],
            'planned_start_to' => ['nullable', 'date'],
            'planned_end_from' => ['nullable', 'date'],
            'planned_end_to' => ['nullable', 'date'],
            'expected_completion_from' => ['nullable', 'date'],
            'expected_completion_to' => ['nullable', 'date'],
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
            'sort' => ['nullable', 'string', 'max:40'],
            'direction' => ['nullable', 'in:asc,desc'],
        ];
    }
}
