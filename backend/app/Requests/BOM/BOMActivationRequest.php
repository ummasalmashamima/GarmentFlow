<?php

declare(strict_types=1);

namespace App\Requests\BOM;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class BOMActivationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasPermission('bom.manage') ?? false;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'version_id' => ['nullable', 'integer', Rule::exists('bom_versions', 'id')],
        ];
    }
}
