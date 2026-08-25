<?php

declare(strict_types=1);

namespace App\Requests\Planning;

use Illuminate\Foundation\Http\FormRequest;

class SupplyPlanGenerateRequest extends FormRequest
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
            ...PlanningRules::period(true),
            ...PlanningRules::availability(),
            'available_quantity' => ['nullable', 'numeric', 'min:0'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
