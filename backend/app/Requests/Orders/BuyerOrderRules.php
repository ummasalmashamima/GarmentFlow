<?php

declare(strict_types=1);

namespace App\Requests\Orders;

use Illuminate\Validation\Rule;

final class BuyerOrderRules
{
    /**
     * @return array<string, array<int, mixed>>
     */
    public static function itemRules(): array
    {
        return [
            'product_id' => ['required', 'integer', Rule::exists('products', 'id')->where(fn ($query) => $query->where('status', 'active')->whereNull('deleted_at'))],
            'product_variant_id' => ['required', 'integer', Rule::exists('product_variants', 'id')->where(fn ($query) => $query->where('status', 'active')->whereNull('deleted_at'))],
            'quantity' => ['required', 'numeric', 'gt:0'],
            'unit_price' => ['required', 'numeric', 'min:0'],
            'remarks' => ['nullable', 'string', 'max:1000'],
        ];
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public static function headerRules(bool $withItems = true): array
    {
        $rules = [
            'buyer_id' => ['required', 'integer', Rule::exists('buyers', 'id')->where(fn ($query) => $query->where('status', 'active')->whereNull('deleted_at'))],
            'order_date' => ['required', 'date_format:Y-m-d'],
            'delivery_date' => ['required', 'date_format:Y-m-d', 'after_or_equal:order_date'],
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
