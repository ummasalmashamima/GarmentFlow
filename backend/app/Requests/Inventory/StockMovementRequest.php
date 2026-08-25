<?php

declare(strict_types=1);

namespace App\Requests\Inventory;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class StockMovementRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasPermission('inventory.manage') ?? false;
    }

    public function rules(): array
    {
        return [
            'warehouse_id' => ['required', 'integer', 'exists:warehouses,id'],
            'warehouse_location_id' => ['nullable', 'integer', 'exists:warehouse_locations,id'],
            'material_id' => ['nullable', 'integer', 'exists:materials,id'],
            'product_id' => ['nullable', 'integer', 'exists:products,id'],
            'product_variant_id' => ['nullable', 'integer', 'exists:product_variants,id'],
            'unit_id' => ['required', 'integer', 'exists:units,id'],
            'quantity' => ['required', 'numeric', 'gt:0'],
            'transaction_date' => ['nullable', 'date'],
            'reference_type' => ['nullable', 'string', 'max:100'],
            'reference_id' => ['nullable', 'integer', 'min:1'],
            'idempotency_key' => ['nullable', 'string', 'max:150'],
            'remarks' => ['nullable', 'string', 'max:1000'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $itemCount = count(array_filter([
                $this->input('material_id'),
                $this->input('product_id'),
                $this->input('product_variant_id'),
            ], static fn ($value): bool => $value !== null && $value !== ''));
            if ($itemCount !== 1 && ! ($this->filled('product_id') && $this->filled('product_variant_id') && $itemCount === 2)) {
                $validator->errors()->add('item', 'Provide exactly one material, product, or product variant.');
            }
        });
    }
}
