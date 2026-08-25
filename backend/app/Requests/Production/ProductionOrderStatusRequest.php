<?php

declare(strict_types=1);

namespace App\Requests\Production;

use Illuminate\Foundation\Http\FormRequest;

class ProductionOrderStatusRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasPermission('production.manage') ?? false;
    }

    /** @return array<string, array<int, string>> */
    public function rules(): array
    {
        return [
            'status' => ['required', 'in:in_progress,cancelled'],
        ];
    }
}
