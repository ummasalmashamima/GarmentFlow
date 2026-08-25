<?php

declare(strict_types=1);

namespace App\Services\Planning;

use App\Models\BomHeader;
use App\Models\BomVersion;
use App\Models\SupplyPlan;
use App\Services\BOM\BOMCalculationService;
use Illuminate\Validation\ValidationException;

final class MaterialRequirementCalculationService
{
    public function __construct(private readonly BOMCalculationService $bomCalculationService) {}

    /**
     * @param  array<string, mixed>  $attributes
     * @return array<string, mixed>
     */
    public function calculate(array $attributes): array
    {
        $supplyPlans = SupplyPlan::query()
            ->with(['product', 'productVariant'])
            ->whereIn('id', array_map('intval', $attributes['supply_plan_ids'] ?? []))
            ->get();

        $requestedIds = array_map('intval', $attributes['supply_plan_ids'] ?? []);
        if (count($requestedIds) === 0 || $supplyPlans->count() !== count(array_unique($requestedIds))) {
            throw ValidationException::withMessages(['supply_plan_ids' => 'All selected Supply Plans must exist.']);
        }

        $availability = $this->availabilityMap($attributes['availability'] ?? []);
        $aggregated = [];
        $planSummaries = [];

        foreach ($supplyPlans as $supplyPlan) {
            $plannedQuantity = (float) $supplyPlan->planned_production_quantity;
            $bom = BomHeader::query()
                ->where('product_id', $supplyPlan->product_id)
                ->where('status', 'active')
                ->with('activeVersion')
                ->first();

            if ($bom === null || $bom->activeVersion === null) {
                throw ValidationException::withMessages([
                    'supply_plan_ids' => "No active BOM version exists for product {$supplyPlan->product->code}.",
                ]);
            }

            $calculation = $plannedQuantity > 0
                ? $this->bomCalculationService->calculate($bom->activeVersion, $plannedQuantity)
                : $this->zeroBomCalculation($bom->activeVersion);
            $planSummaries[] = [
                'id' => $supplyPlan->getKey(),
                'product' => [
                    'id' => $supplyPlan->product->getKey(),
                    'code' => $supplyPlan->product->code,
                    'name' => $supplyPlan->product->name,
                ],
                'product_variant' => $supplyPlan->productVariant === null ? null : [
                    'id' => $supplyPlan->productVariant->getKey(),
                    'code' => $supplyPlan->productVariant->sku,
                    'name' => $supplyPlan->productVariant->variant_name,
                ],
                'planned_production_quantity' => round($plannedQuantity, 4),
                'bom_version_id' => $bom->activeVersion->getKey(),
                'bom_version_number' => $bom->activeVersion->version_number,
            ];

            foreach ($calculation['lines'] as $line) {
                $materialId = (int) $line['material']['id'];
                $unitId = (int) $line['unit']['id'];
                $aggregateKey = $materialId.':'.$unitId;
                $source = [
                    'supply_plan_id' => $supplyPlan->getKey(),
                    'product_id' => $supplyPlan->product_id,
                    'product_variant_id' => $supplyPlan->product_variant_id,
                    'bom_version_id' => $bom->activeVersion->getKey(),
                    'bom_item_id' => $line['item_id'],
                    'material_id' => $materialId,
                    'unit_id' => $unitId,
                    'planned_product_quantity' => round($plannedQuantity, 4),
                    'bom_quantity' => $line['bom_quantity'],
                    'wastage_percentage' => $line['wastage_percentage'],
                    'gross_quantity' => $line['required_quantity'],
                ];

                if (! isset($aggregated[$aggregateKey])) {
                    $aggregated[$aggregateKey] = [
                        'material' => $line['material'],
                        'unit' => $line['unit'],
                        'gross_quantity' => 0.0,
                        'sources' => [],
                    ];
                }
                $aggregated[$aggregateKey]['gross_quantity'] += (float) $line['required_quantity'];
                $aggregated[$aggregateKey]['sources'][] = $source;
            }
        }

        $inventoryDataAvailable = count($availability) > 0;
        $lines = array_values(array_map(function (array $line) use ($availability): array {
            $key = (int) $line['material']['id'].':'.(int) $line['unit']['id'];
            $materialAvailability = $availability[$key] ?? null;
            $availableQuantity = $materialAvailability['available_quantity'] ?? null;
            $allocatedQuantity = $materialAvailability['allocated_quantity'] ?? null;
            $netQuantity = $availableQuantity === null
                ? null
                : max($line['gross_quantity'] - $availableQuantity - ($allocatedQuantity ?? 0.0), 0);

            return [
                'material' => $line['material'],
                'unit' => $line['unit'],
                'gross_quantity' => round($line['gross_quantity'], 4),
                'available_quantity' => $availableQuantity === null ? null : round($availableQuantity, 4),
                'allocated_quantity' => $allocatedQuantity === null ? null : round($allocatedQuantity, 4),
                'net_quantity' => $netQuantity === null ? null : round($netQuantity, 4),
                'status' => $netQuantity === null ? 'pending_inventory' : 'calculated',
                'sources' => $line['sources'],
            ];
        }, $aggregated));

        $totalGross = array_sum(array_column($lines, 'gross_quantity'));
        $netValues = array_column($lines, 'net_quantity');
        $allNetValuesAvailable = $netValues !== [] && count(array_filter($netValues, static fn ($value): bool => $value === null)) === 0;

        return [
            'inventory_data_available' => $inventoryDataAvailable,
            'supply_plans' => $planSummaries,
            'lines' => $lines,
            'total_gross_quantity' => round($totalGross, 4),
            'total_net_quantity' => $allNetValuesAvailable ? round(array_sum($netValues), 4) : null,
            'total_lines' => count($lines),
        ];
    }

    /**
     * @return array{lines: array<int, array<string, mixed>>}
     */
    private function zeroBomCalculation(BomVersion $version): array
    {
        $version->loadMissing(['items.material', 'items.unit']);
        $lines = $version->items->map(static fn ($item): array => [
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
            'bom_quantity' => round((float) $item->quantity, 4),
            'wastage_percentage' => round((float) $item->wastage_percentage, 4),
            'required_quantity' => 0.0,
        ])->values()->all();

        return ['lines' => $lines];
    }

    /**
     * @param  array<int, array<string, mixed>>  $rows
     * @return array<string, array{available_quantity: float, allocated_quantity: float}>
     */
    private function availabilityMap(array $rows): array
    {
        $map = [];
        foreach ($rows as $row) {
            $materialId = (int) ($row['material_id'] ?? 0);
            $unitId = (int) ($row['unit_id'] ?? 0);
            $map[$materialId.':'.$unitId] = [
                'available_quantity' => (float) ($row['available_quantity'] ?? 0),
                'allocated_quantity' => (float) ($row['allocated_quantity'] ?? 0),
            ];
        }

        return $map;
    }
}
