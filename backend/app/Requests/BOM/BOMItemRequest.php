<?php

declare(strict_types=1);

namespace App\Requests\BOM;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class BOMItemRequest extends FormRequest
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
            'material_id' => [
                'required',
                'integer',
                Rule::exists('materials', 'id')->where(fn ($query) => $query->where('status', 'active')->whereNull('deleted_at')),
            ],
            'unit_id' => [
                'required',
                'integer',
                Rule::exists('units', 'id')->where(fn ($query) => $query->where('status', 'active')->whereNull('deleted_at')),
            ],
            'quantity' => ['required', 'numeric', 'gt:0'],
            'wastage_percentage' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'line_number' => ['nullable', 'integer', 'min:1'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
