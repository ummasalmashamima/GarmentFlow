<?php

declare(strict_types=1);

namespace App\Requests\Planning;

use Illuminate\Foundation\Http\FormRequest;

class SupplyPlanRecalculateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasPermission('planning.manage') ?? false;
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'available_quantity' => ['nullable', 'numeric', 'min:0'],
        ];
    }
}
