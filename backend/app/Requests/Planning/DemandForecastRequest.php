<?php

declare(strict_types=1);

namespace App\Requests\Planning;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class DemandForecastRequest extends FormRequest
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
            'method' => ['required', Rule::in(['historical_average', 'manual'])],
            'forecast_quantity' => [Rule::requiredIf($this->input('method') === 'manual'), 'nullable', 'numeric', 'min:0'],
            'forecast_date' => ['nullable', 'date'],
            'confidence_score' => ['nullable', 'numeric', 'between:0,100'],
            'accuracy_score' => ['nullable', 'numeric', 'between:0,100'],
            'lookback_periods' => ['nullable', 'integer', 'min:1', 'max:24'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
