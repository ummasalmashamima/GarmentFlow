<?php

declare(strict_types=1);

namespace App\Requests\BOM;

use Illuminate\Foundation\Http\FormRequest;

class BOMCalculateRequest extends FormRequest
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
            'order_quantity' => ['required', 'numeric', 'gt:0'],
        ];
    }
}
