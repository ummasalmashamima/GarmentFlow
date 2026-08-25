<?php

declare(strict_types=1);

namespace App\Services\Finance;

final class InvoiceCalculationService
{
    /**
     * @param  array<int, array<string, mixed>>  $items
     * @return array{subtotal: string, discount_amount: string, tax_amount: string, total_amount: string, items: array<int, array<string, mixed>>}
     */
    public function calculate(array $items): array
    {
        $subtotal = 0.0;
        $discountTotal = 0.0;
        $taxTotal = 0.0;
        $calculatedItems = [];

        foreach ($items as $item) {
            $quantity = round((float) ($item['quantity'] ?? 0), 4);
            $unitPrice = round((float) ($item['unit_price'] ?? 0), 4);
            $discount = round((float) ($item['discount_amount'] ?? $item['discount'] ?? 0), 4);
            $tax = round((float) ($item['tax_amount'] ?? $item['tax'] ?? 0), 4);
            $gross = round($quantity * $unitPrice, 4);
            $lineTotal = round(max($gross - $discount + $tax, 0), 4);
            $subtotal += $gross;
            $discountTotal += $discount;
            $taxTotal += $tax;
            $calculatedItems[] = [
                'sales_order_item_id' => (int) $item['sales_order_item_id'],
                'line_number' => (int) ($item['line_number'] ?? count($calculatedItems) + 1),
                'product_id' => (int) $item['product_id'],
                'product_variant_id' => isset($item['product_variant_id']) && $item['product_variant_id'] !== '' ? (int) $item['product_variant_id'] : null,
                'unit_id' => (int) $item['unit_id'],
                'quantity' => $this->decimal($quantity),
                'unit_price' => $this->decimal($unitPrice),
                'discount_amount' => $this->decimal($discount),
                'tax_amount' => $this->decimal($tax),
                'line_total' => $this->decimal($lineTotal),
                'remarks' => $item['remarks'] ?? null,
            ];
        }

        $subtotal = round($subtotal, 4);
        $discountTotal = round($discountTotal, 4);
        $taxTotal = round($taxTotal, 4);

        return [
            'subtotal' => $this->decimal($subtotal),
            'discount_amount' => $this->decimal($discountTotal),
            'tax_amount' => $this->decimal($taxTotal),
            'total_amount' => $this->decimal(max($subtotal - $discountTotal + $taxTotal, 0)),
            'items' => $calculatedItems,
        ];
    }

    private function decimal(float|int|string $value): string
    {
        return number_format((float) $value, 4, '.', '');
    }
}
