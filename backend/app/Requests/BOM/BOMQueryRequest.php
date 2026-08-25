<?php

declare(strict_types=1);

namespace App\Requests\BOM;

use Illuminate\Foundation\Http\FormRequest;

class BOMQueryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasPermission('bom.view') ?? false;
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'search' => ['nullable', 'string', 'max:100'],
            'status' => ['nullable', 'in:draft,active,inactive'],
            'sort' => ['nullable', 'in:id,code,name,status,created_at'],
            'direction' => ['nullable', 'in:asc,desc'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ];
    }
}
