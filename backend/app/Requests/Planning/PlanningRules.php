<?php

declare(strict_types=1);

namespace App\Requests\Planning;

final class PlanningRules
{
    /**
     * @return array<string, array<int, string>>
     */
    public static function period(bool $productRequired = false): array
    {
        return [
            'product_id' => $productRequired ? ['required', 'integer', 'exists:products,id'] : ['nullable', 'integer', 'exists:products,id'],
            'product_variant_id' => ['nullable', 'integer', 'exists:product_variants,id'],
            'period_type' => ['required', 'in:weekly,monthly,quarterly'],
            'period_start' => ['required', 'date'],
            'period_end' => ['required', 'date', 'after_or_equal:period_start'],
        ];
    }

    /**
     * @return array<string, array<int, string>>
     */
    public static function pagination(array $extra = []): array
    {
        return array_merge([
            'search' => ['nullable', 'string', 'max:100'],
            'status' => ['nullable', 'string', 'max:30'],
            'period_type' => ['nullable', 'in:weekly,monthly,quarterly'],
            'product_id' => ['nullable', 'integer', 'exists:products,id'],
            'product_variant_id' => ['nullable'],
            'period_start_from' => ['nullable', 'date'],
            'period_start_to' => ['nullable', 'date', 'after_or_equal:period_start_from'],
            'period_end_from' => ['nullable', 'date'],
            'period_end_to' => ['nullable', 'date', 'after_or_equal:period_end_from'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
            'sort' => ['nullable', 'string', 'max:50'],
            'direction' => ['nullable', 'in:asc,desc'],
        ], $extra);
    }

    /**
     * @return array<string, array<int, string>>
     */
    public static function availability(bool $required = false): array
    {
        return [
            'availability' => [$required ? 'required' : 'nullable', 'array'],
            'availability.*.material_id' => ['required_with:availability', 'integer', 'exists:materials,id'],
            'availability.*.unit_id' => ['required_with:availability', 'integer', 'exists:units,id'],
            'availability.*.available_quantity' => ['required_with:availability', 'numeric', 'min:0'],
            'availability.*.allocated_quantity' => ['nullable', 'numeric', 'min:0'],
        ];
    }
}
