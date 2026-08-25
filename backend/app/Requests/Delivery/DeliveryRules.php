<?php

declare(strict_types=1);

namespace App\Requests\Delivery;

final class DeliveryRules
{
    /** @return array<string, array<int, mixed>> */
    public static function create(): array
    {
        return [
            'sales_order_id' => ['required', 'integer', 'exists:sales_orders,id'],
            'warehouse_id' => ['nullable', 'integer', 'exists:warehouses,id'],
            'delivery_date' => ['required', 'date'],
            'expected_delivery_date' => ['nullable', 'date', 'after_or_equal:delivery_date'],
            'carrier_name' => ['nullable', 'string', 'max:160'],
            'tracking_number' => ['nullable', 'string', 'max:160'],
            'delivery_address' => ['nullable', 'string'],
            'contact_information' => ['nullable', 'string', 'max:255'],
            'remarks' => ['nullable', 'string'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.sales_order_item_id' => ['required', 'integer', 'exists:sales_order_items,id'],
            'items.*.product_id' => ['nullable', 'integer', 'exists:products,id'],
            'items.*.product_variant_id' => ['nullable', 'integer', 'exists:product_variants,id'],
            'items.*.unit_id' => ['nullable', 'integer', 'exists:units,id'],
            'items.*.delivery_quantity' => ['required', 'numeric', 'gt:0'],
            'items.*.remarks' => ['nullable', 'string'],
        ];
    }

    /** @return array<string, array<int, mixed>> */
    public static function update(): array
    {
        return [
            'delivery_date' => ['sometimes', 'date'],
            'expected_delivery_date' => ['nullable', 'date', 'after_or_equal:delivery_date'],
            'carrier_name' => ['nullable', 'string', 'max:160'],
            'tracking_number' => ['nullable', 'string', 'max:160'],
            'delivery_address' => ['nullable', 'string'],
            'contact_information' => ['nullable', 'string', 'max:255'],
            'remarks' => ['nullable', 'string'],
        ];
    }

    /** @return array<string, array<int, mixed>> */
    public static function remarks(): array
    {
        return ['remarks' => ['nullable', 'string']];
    }
}
