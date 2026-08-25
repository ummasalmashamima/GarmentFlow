<?php

declare(strict_types=1);

namespace App\Requests\Inventory;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class StockAdjustmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasPermission('inventory.adjust') ?? false;
    }

    public function rules(): array
    {
        return [
            'direction' => ['required', 'in:IN,OUT'],
            'warehouse_id' => ['required', 'integer', 'exists:warehouses,id'],
            'warehouse_location_id' => ['nullable', 'integer', 'exists:warehouse_locations,id'],
            'adjustment_date' => ['nullable', 'date'],
            'reason' => ['required', 'string', 'min:3', 'max:1000'],
            'remarks' => ['nullable', 'string', 'max:1000'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.material_id' => ['nullable', 'integer', 'exists:materials,id'],
            'items.*.product_id' => ['nullable', 'integer', 'exists:products,id'],
            'items.*.product_variant_id' => ['nullable', 'integer', 'exists:product_variants,id'],
            'items.*.unit_id' => ['required', 'integer', 'exists:units,id'],
            'items.*.quantity' => ['required', 'numeric', 'gt:0'],
            'items.*.remarks' => ['nullable', 'string', 'max:500'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            foreach ((array) $this->input('items', []) as $index => $item) {
                $count = count(array_filter([
                    $item['material_id'] ?? null,
                    $item['product_id'] ?? null,
                    $item['product_variant_id'] ?? null,
                ], static fn ($value): bool => $value !== null && $value !== ''));
                if ($count !== 1 && ! (isset($item['product_id'], $item['product_variant_id']) && $count === 2)) {
                    $validator->errors()->add("items.{$index}.item", 'Provide exactly one material, product, or product variant.');
                }
            }
        });
    }
}
