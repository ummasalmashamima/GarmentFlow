<?php

declare(strict_types=1);

namespace App\Requests\BOM;

use App\Models\BomHeader;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class BOMHeaderRequest extends FormRequest
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
        $bom = $this->route('bom');
        $bomId = $bom instanceof BomHeader ? $bom->getKey() : $bom;
        $rules = [
            'code' => ['required', 'string', 'max:80', Rule::unique('bom_headers', 'code')->ignore($bomId)],
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:2000'],
        ];

        if ($this->isMethod('post')) {
            $rules['product_id'] = [
                'required',
                'integer',
                Rule::exists('products', 'id')->where(fn ($query) => $query->where('status', 'active')->whereNull('deleted_at')),
                Rule::unique('bom_headers', 'product_id'),
            ];
            $rules['effective_from'] = ['required', 'date_format:Y-m-d'];
            $rules['effective_to'] = ['nullable', 'date_format:Y-m-d', 'after_or_equal:effective_from'];
            $rules['version_notes'] = ['nullable', 'string', 'max:2000'];
        }

        return $rules;
    }
}
