<?php

declare(strict_types=1);

namespace App\Requests\Orders;

use Illuminate\Foundation\Http\FormRequest;

abstract class BuyerOrderActionRequest extends FormRequest
{
    abstract protected function requiredPermission(): string;

    public function authorize(): bool
    {
        return $this->user()?->hasPermission($this->requiredPermission()) ?? false;
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'remarks' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
