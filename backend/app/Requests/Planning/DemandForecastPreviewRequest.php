<?php

declare(strict_types=1);

namespace App\Requests\Planning;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class DemandForecastPreviewRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasPermission('planning.view') ?? false;
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
            'lookback_periods' => ['nullable', 'integer', 'min:1', 'max:24'],
        ];
    }
}
