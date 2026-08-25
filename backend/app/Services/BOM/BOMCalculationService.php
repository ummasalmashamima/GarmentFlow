<?php

declare(strict_types=1);

namespace App\Services\BOM;

use App\Models\BomVersion;
use Illuminate\Validation\ValidationException;

final class BOMCalculationService
{
    /**
     * @return array<string, mixed>
     */
    public function calculate(BomVersion $version, int|float|string $orderQuantity): array
    {
        $orderQuantity = (float) $orderQuantity;

        if ($orderQuantity <= 0) {
            throw ValidationException::withMessages([
                'order_quantity' => 'Order quantity must be greater than zero.',
            ]);
        }

        $version->loadMissing(['bomHeader.product', 'items.material', 'items.unit']);
        $wastageFactorByPercentage = static fn (float $percentage): float => 1 + ($percentage / 100);

        $lines = $version->items->map(function ($item) use ($orderQuantity, $wastageFactorByPercentage): array {
            $quantity = (float) $item->quantity;
            $wastagePercentage = (float) $item->wastage_percentage;
            $wastageFactor = $wastageFactorByPercentage($wastagePercentage);

            return [
                'item_id' => $item->getKey(),
                'material' => [
                    'id' => $item->material->getKey(),
                    'code' => $item->material->code,
                    'name' => $item->material->name,
                ],
                'unit' => [
                    'id' => $item->unit->getKey(),
                    'code' => $item->unit->code,
                    'name' => $item->unit->name,
                    'symbol' => $item->unit->symbol,
                ],
                'bom_quantity' => round($quantity, 4),
                'order_quantity' => round($orderQuantity, 4),
                'wastage_percentage' => round($wastagePercentage, 4),
                'wastage_factor' => round($wastageFactor, 4),
                'required_quantity' => round($quantity * $orderQuantity * $wastageFactor, 4),
            ];
        })->values()->all();

        return [
            'bom' => [
                'id' => $version->bomHeader->getKey(),
                'code' => $version->bomHeader->code,
                'name' => $version->bomHeader->name,
                'product' => [
                    'id' => $version->bomHeader->product->getKey(),
                    'code' => $version->bomHeader->product->code,
                    'name' => $version->bomHeader->product->name,
                ],
            ],
            'version' => [
                'id' => $version->getKey(),
                'version_number' => $version->version_number,
                'effective_from' => $version->effective_from?->toDateString(),
                'effective_to' => $version->effective_to?->toDateString(),
                'status' => $version->status,
            ],
            'order_quantity' => round($orderQuantity, 4),
            'lines' => $lines,
            'total_lines' => count($lines),
        ];
    }
}
