<?php

declare(strict_types=1);

namespace App\Requests\Production;

use Illuminate\Foundation\Http\FormRequest;

class ProductionCompletionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasPermission('production.manage') ?? false;
    }

    /** @return array<string, array<int, string>> */
    public function rules(): array
    {
        return [
            'finished_quantity' => ['required', 'numeric', 'gt:0'],
            'completed_quantity' => ['nullable', 'numeric', 'min:0'],
            'rejected_quantity' => ['nullable', 'numeric', 'min:0'],
            'finished_date' => ['required', 'date'],
            'remarks' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
