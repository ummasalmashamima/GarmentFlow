<?php

declare(strict_types=1);

namespace App\Requests\Reporting;

use Illuminate\Foundation\Http\FormRequest;

class AlertStateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasPermission('alerts.manage') ?? false;
    }

    /** @return array<string, array<int, string>> */
    public function rules(): array
    {
        return ['read' => ['required', 'boolean']];
    }
}
