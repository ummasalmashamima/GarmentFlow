<?php

declare(strict_types=1);

namespace App\Requests\Planning;

use Illuminate\Foundation\Http\FormRequest;

class MaterialRequirementRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasPermission('planning.manage') ?? false;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'supply_plan_ids' => ['required', 'array', 'min:1'],
            'supply_plan_ids.*' => ['integer', 'distinct', 'exists:supply_plans,id'],
            'planning_date' => ['nullable', 'date'],
            ...PlanningRules::availability(),
            'notes' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
