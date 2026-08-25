<?php

declare(strict_types=1);

namespace App\Requests\Production;

use Illuminate\Foundation\Http\FormRequest;

class ProductionPlanStatusRequest extends FormRequest
{
    public function authorize(): bool
    {
        return ($this->user()?->hasPermission('production.manage') ?? false) || ($this->user()?->hasPermission('production.approve') ?? false);
    }

    /** @return array<string, array<int, string>> */
    public function rules(): array
    {
        return [
            'status' => ['required', 'in:approved,scheduled,cancelled'],
        ];
    }
}
