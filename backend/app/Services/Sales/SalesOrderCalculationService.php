<?php

declare(strict_types=1);

namespace App\Services\Sales;

final class SalesOrderCalculationService
{
    /**
     * @param  array<int, array<string, mixed>>  $items
     * @return array{subtotal: string, total_quantity: string, discount_amount: string, tax_amount: string, total_amount: string, items: array<int, array<string, mixed>>}
     */
    public function calculate(array $items, float|int|string $orderDiscount = 0, float|int|string $orderTax = 0): array
    {
        $subtotal = 0.0;
        $totalQuantity = 0.0;
        $lineDiscountTotal = 0.0;
        $lineTaxTotal = 0.0;
        $calculatedItems = [];

        foreach ($items as $item) {
            $quantity = round((float) ($item['ordered_quantity'] ?? $item['quantity'] ?? 0), 4);
            $unitPrice = round((float) ($item['unit_price'] ?? 0), 4);
            $discount = round((float) ($item['discount_amount'] ?? $item['discount'] ?? 0), 4);
            $tax = round((float) ($item['tax_amount'] ?? $item['tax'] ?? 0), 4);
            $gross = round($quantity * $unitPrice, 4);
            $lineTotal = $gross;
            $subtotal += $gross;
            $totalQuantity += $quantity;
            $lineDiscountTotal += $discount;
            $lineTaxTotal += $tax;
            $calculatedItems[] = [
                'product_id' => (int) $item['product_id'],
                'product_variant_id' => isset($item['product_variant_id']) && $item['product_variant_id'] !== '' ? (int) $item['product_variant_id'] : null,
                'unit_id' => (int) $item['unit_id'],
                'ordered_quantity' => $this->decimal($quantity),
                'confirmed_quantity' => $this->decimal((float) ($item['confirmed_quantity'] ?? 0)),
                'delivered_quantity' => $this->decimal((float) ($item['delivered_quantity'] ?? 0)),
                'remaining_quantity' => $this->decimal(max($quantity - (float) ($item['delivered_quantity'] ?? 0), 0)),
                'unit_price' => $this->decimal($unitPrice),
                'discount_amount' => $this->decimal($discount),
                'tax_amount' => $this->decimal($tax),
                'line_total' => $this->decimal($lineTotal),
                'remarks' => $item['remarks'] ?? null,
            ];
        }

        $orderDiscount = round((float) $orderDiscount, 4);
        $orderTax = round((float) $orderTax, 4);
        $discountTotal = round($lineDiscountTotal + $orderDiscount, 4);
        $taxTotal = round($lineTaxTotal + $orderTax, 4);
        $totalAmount = round(max($subtotal - $discountTotal + $taxTotal, 0), 4);

        return [
            'subtotal' => $this->decimal($subtotal),
            'total_quantity' => $this->decimal($totalQuantity),
            'discount_amount' => $this->decimal($discountTotal),
            'tax_amount' => $this->decimal($taxTotal),
            'total_amount' => $this->decimal($totalAmount),
            'items' => $calculatedItems,
        ];
    }

    /** @param array<string, mixed> $item */
    public function lineTotal(array $item): float
    {
        $quantity = (float) ($item['ordered_quantity'] ?? $item['quantity'] ?? 0);
        $unitPrice = (float) ($item['unit_price'] ?? 0);
        $discount = (float) ($item['discount_amount'] ?? $item['discount'] ?? 0);
        $tax = (float) ($item['tax_amount'] ?? $item['tax'] ?? 0);

        return round($quantity * $unitPrice, 4);
    }

    private function decimal(float|int|string $value): string
    {
        return number_format((float) $value, 4, '.', '');
    }
}
