<?php

declare(strict_types=1);

namespace App\Requests\Reporting;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AlertListRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasPermission('alerts.view') ?? false;
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        return [
            'severity' => ['nullable', Rule::in(['info', 'warning', 'critical'])],
            'rule_code' => ['nullable', 'string', 'max:80'],
            'read' => ['nullable', 'boolean'],
            'include_resolved' => ['nullable', 'boolean'],
            'search' => ['nullable', 'string', 'max:100'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
            'page' => ['nullable', 'integer', 'min:1'],
        ];
    }
}
