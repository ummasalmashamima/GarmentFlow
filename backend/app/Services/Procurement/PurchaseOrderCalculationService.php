<?php

declare(strict_types=1);

namespace App\Services\Procurement;

final class PurchaseOrderCalculationService
{
    /**
     * @param  array<string, mixed>  $item
     */
    public function lineTotal(array $item): float
    {
        return round((float) ($item['quantity'] ?? 0) * (float) ($item['unit_price'] ?? 0), 4);
    }

    /**
     * @param  array<int, array<string, mixed>>  $items
     * @return array{subtotal: float, tax_total: float, discount_total: float, total_amount: float, items: array<int, array<string, mixed>>}
     */
    public function calculate(array $items, float $taxTotal = 0.0, float $discountTotal = 0.0): array
    {
        $calculatedItems = array_map(function (array $item): array {
            $item['line_total'] = $this->lineTotal($item);

            return $item;
        }, $items);
        $subtotal = round(array_sum(array_column($calculatedItems, 'line_total')), 4);
        $taxTotal = round($taxTotal, 4);
        $discountTotal = round($discountTotal, 4);

        return [
            'subtotal' => $subtotal,
            'tax_total' => $taxTotal,
            'discount_total' => $discountTotal,
            'total_amount' => round($subtotal + $taxTotal - $discountTotal, 4),
            'items' => $calculatedItems,
        ];
    }
}
