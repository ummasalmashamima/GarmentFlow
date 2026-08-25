<?php

declare(strict_types=1);

namespace App\Services\Planning;

use App\Models\Product;
use App\Models\ProductVariant;
use App\Services\Orders\BuyerOrderWorkflow;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class ForecastCalculationService
{
    public function __construct(
        private readonly PlanningPeriodService $periodService,
        private readonly BuyerOrderWorkflow $workflow,
    ) {}

    /**
     * @param  array<string, mixed>  $attributes
     * @return array<string, mixed>
     */
    public function calculate(array $attributes): array
    {
        $product = Product::query()->whereKey((int) $attributes['product_id'])->where('status', 'active')->first();
        if ($product === null) {
            throw ValidationException::withMessages(['product_id' => 'The selected product must exist and be active.']);
        }

        $variant = null;
        if (($attributes['product_variant_id'] ?? null) !== null && $attributes['product_variant_id'] !== '') {
            $variant = ProductVariant::query()
                ->whereKey((int) $attributes['product_variant_id'])
                ->where('product_id', $product->getKey())
                ->where('status', 'active')
                ->first();
            if ($variant === null) {
                throw ValidationException::withMessages(['product_variant_id' => 'The selected Product Variant must belong to the selected active product.']);
            }
        }

        $period = $this->periodService->normalize($attributes);
        $method = (string) ($attributes['method'] ?? 'historical_average');
        $lookbackPeriods = (int) ($attributes['lookback_periods'] ?? 3);
        $historicalPeriods = $this->periodService->previousPeriods($period['period_type'], $period['period_start'], $lookbackPeriods);
        $historicalDemand = [];

        foreach ($historicalPeriods as $historicalPeriod) {
            $historicalDemand[] = [
                ...$historicalPeriod,
                'demand_quantity' => $this->firmOrderQuantity(
                    $product->getKey(),
                    $variant?->getKey(),
                    $historicalPeriod['period_start'],
                    $historicalPeriod['period_end'],
                ),
            ];
        }

        $averageQuantity = count($historicalDemand) === 0
            ? 0.0
            : array_sum(array_column($historicalDemand, 'demand_quantity')) / count($historicalDemand);
        $forecastQuantity = $method === 'manual'
            ? (float) ($attributes['forecast_quantity'] ?? 0)
            : $averageQuantity;

        return [
            'product' => [
                'id' => $product->getKey(),
                'code' => $product->code,
                'name' => $product->name,
            ],
            'product_variant' => $variant === null ? null : [
                'id' => $variant->getKey(),
                'code' => $variant->sku,
                'name' => $variant->variant_name,
            ],
            'period_type' => $period['period_type'],
            'period_start' => $period['period_start'],
            'period_end' => $period['period_end'],
            'method' => $method,
            'lookback_periods' => $lookbackPeriods,
            'historical_periods' => $historicalDemand,
            'average_quantity' => round($averageQuantity, 4),
            'forecast_quantity' => round(max($forecastQuantity, 0), 4),
            'source_statuses' => $this->workflow->firmDemandStatuses(),
        ];
    }

    private function firmOrderQuantity(int $productId, ?int $variantId, string $periodStart, string $periodEnd): float
    {
        $query = DB::table('buyer_order_items')
            ->join('buyer_orders', 'buyer_orders.id', '=', 'buyer_order_items.buyer_order_id')
            ->where('buyer_order_items.product_id', $productId)
            ->whereIn('buyer_orders.status', $this->workflow->firmDemandStatuses())
            ->whereBetween('buyer_orders.delivery_date', [$periodStart, $periodEnd])
            ->whereNull('buyer_orders.deleted_at');

        if ($variantId !== null) {
            $query->where('buyer_order_items.product_variant_id', $variantId);
        }

        return round((float) ($query->sum('buyer_order_items.quantity') ?? 0), 4);
    }
}
