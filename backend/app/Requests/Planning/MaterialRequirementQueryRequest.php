<?php

declare(strict_types=1);

namespace App\Requests\Planning;

use Illuminate\Foundation\Http\FormRequest;

class MaterialRequirementQueryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasPermission('planning.view') ?? false;
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'search' => ['nullable', 'string', 'max:100'],
            'status' => ['nullable', 'in:calculated'],
            'planning_date_from' => ['nullable', 'date'],
            'planning_date_to' => ['nullable', 'date', 'after_or_equal:planning_date_from'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
            'sort' => ['nullable', 'in:id,run_number,planning_date,total_gross_quantity,status,created_at'],
            'direction' => ['nullable', 'in:asc,desc'],
        ];
    }
}
