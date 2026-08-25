<?php

declare(strict_types=1);

namespace App\Services\Orders;

final class BuyerOrderCalculationService
{
    /**
     * @param  array<int, array<string, mixed>>  $items
     * @return array{total_quantity: string, total_amount: string, items: array<int, array<string, mixed>>}
     */
    public function calculate(array $items): array
    {
        $totalQuantity = 0.0;
        $totalAmount = 0.0;
        $calculatedItems = [];

        foreach ($items as $item) {
            $quantity = round((float) $item['quantity'], 4);
            $unitPrice = round((float) $item['unit_price'], 4);
            $itemTotal = round($quantity * $unitPrice, 4);
            $totalQuantity += $quantity;
            $totalAmount += $itemTotal;
            $calculatedItems[] = [
                'product_id' => (int) $item['product_id'],
                'product_variant_id' => (int) $item['product_variant_id'],
                'quantity' => $this->decimal($quantity),
                'unit_price' => $this->decimal($unitPrice),
                'item_total' => $this->decimal($itemTotal),
                'remarks' => $item['remarks'] ?? null,
            ];
        }

        return [
            'total_quantity' => $this->decimal($totalQuantity),
            'total_amount' => $this->decimal($totalAmount),
            'items' => $calculatedItems,
        ];
    }

    /**
     * @param  array<string, mixed>  $item
     */
    public function lineTotal(array $item): float
    {
        return round((float) $item['quantity'] * (float) $item['unit_price'], 4);
    }

    private function decimal(float|int|string $value): string
    {
        return number_format((float) $value, 4, '.', '');
    }
}
