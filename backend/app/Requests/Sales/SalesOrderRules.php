<?php

declare(strict_types=1);

namespace App\Requests\Sales;

use Illuminate\Validation\Rule;

final class SalesOrderRules
{
    /** @return array<string, array<int, mixed>> */
    public static function itemRules(): array
    {
        return [
            'product_id' => ['required', 'integer', Rule::exists('products', 'id')->where(fn ($query) => $query->where('status', 'active')->whereNull('deleted_at'))],
            'product_variant_id' => ['nullable', 'integer', Rule::exists('product_variants', 'id')->where(fn ($query) => $query->where('status', 'active')->whereNull('deleted_at'))],
            'unit_id' => ['required', 'integer', Rule::exists('units', 'id')->where(fn ($query) => $query->where('status', 'active')->whereNull('deleted_at'))],
            'ordered_quantity' => ['required', 'numeric', 'gt:0'],
            'unit_price' => ['required', 'numeric', 'min:0'],
            'discount_amount' => ['nullable', 'numeric', 'min:0'],
            'tax_amount' => ['nullable', 'numeric', 'min:0'],
            'remarks' => ['nullable', 'string', 'max:1000'],
        ];
    }

    /** @return array<string, array<int, mixed>> */
    public static function headerRules(bool $withItems = true): array
    {
        $rules = [
            'buyer_id' => ['nullable', 'integer', Rule::exists('buyers', 'id')->where(fn ($query) => $query->where('status', 'active')->whereNull('deleted_at'))],
            'customer_id' => ['nullable', 'integer', Rule::exists('customers', 'id')->where(fn ($query) => $query->where('status', 'active')->whereNull('deleted_at'))],
            'order_date' => ['required', 'date_format:Y-m-d'],
            'required_delivery_date' => ['required', 'date_format:Y-m-d', 'after_or_equal:order_date'],
            'warehouse_id' => ['required', 'integer', Rule::exists('warehouses', 'id')->where(fn ($query) => $query->where('status', 'active')->whereNull('deleted_at'))],
            'delivery_address' => ['nullable', 'string', 'max:4000'],
            'contact_information' => ['nullable', 'string', 'max:2000'],
            'order_discount_amount' => ['nullable', 'numeric', 'min:0'],
            'order_tax_amount' => ['nullable', 'numeric', 'min:0'],
            'remarks' => ['nullable', 'string', 'max:2000'],
        ];

        if ($withItems) {
            $rules['items'] = ['required', 'array', 'min:1'];
            foreach (self::itemRules() as $field => $fieldRules) {
                $rules["items.*.{$field}"] = $fieldRules;
            }
        }

        return $rules;
    }
}
