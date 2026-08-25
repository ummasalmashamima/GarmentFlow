<?php

declare(strict_types=1);

namespace App\Requests\Procurement;

use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class PurchaseRequisitionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() instanceof User && $this->user()->hasPermission('procurement.manage');
    }

    public function rules(): array
    {
        return [
            'request_date' => ['required', 'date'],
            'department_id' => ['nullable', 'integer', 'exists:departments,id'],
            'source' => ['nullable', 'string', 'max:100'],
            'required_date' => ['required', 'date', 'after_or_equal:request_date'],
            'priority' => ['required', Rule::in(['low', 'normal', 'high', 'urgent'])],
            'remarks' => ['nullable', 'string', 'max:5000'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.material_id' => ['required', 'integer', Rule::exists('materials', 'id')->where(fn ($query) => $query->where('status', 'active'))],
            'items.*.unit_id' => ['required', 'integer', Rule::exists('units', 'id')->where(fn ($query) => $query->where('status', 'active'))],
            'items.*.material_requirement_id' => ['nullable', 'integer', 'exists:material_requirements,id'],
            'items.*.quantity' => ['required', 'numeric', 'gt:0'],
            'items.*.remarks' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
