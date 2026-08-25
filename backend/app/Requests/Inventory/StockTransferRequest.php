<?php

declare(strict_types=1);

namespace App\Requests\Inventory;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class StockTransferRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasPermission('inventory.manage') ?? false;
    }

    public function rules(): array
    {
        return [
            'source_warehouse_id' => ['required', 'integer', 'exists:warehouses,id'],
            'source_location_id' => ['nullable', 'integer', 'exists:warehouse_locations,id'],
            'destination_warehouse_id' => ['required', 'integer', 'exists:warehouses,id'],
            'destination_location_id' => ['nullable', 'integer', 'exists:warehouse_locations,id'],
            'transfer_date' => ['nullable', 'date'],
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
            if ((string) $this->input('source_warehouse_id') === (string) $this->input('destination_warehouse_id')
                && (string) ($this->input('source_location_id') ?? '') === (string) ($this->input('destination_location_id') ?? '')) {
                $validator->errors()->add('destination_warehouse_id', 'The source and destination stock locations must be different.');
            }
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
