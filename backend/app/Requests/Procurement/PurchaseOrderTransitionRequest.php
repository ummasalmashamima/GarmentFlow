<?php

declare(strict_types=1);

namespace App\Requests\Procurement;

use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;

class PurchaseOrderTransitionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() instanceof User && ($this->user()->hasPermission('procurement.manage') || $this->user()->hasPermission('procurement.approve'));
    }

    public function rules(): array
    {
        return ['remarks' => ['nullable', 'string', 'max:2000']];
    }
}
