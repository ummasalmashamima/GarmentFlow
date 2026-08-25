<?php

declare(strict_types=1);

namespace App\Services\Planning;

use App\Models\Product;
use App\Models\ProductVariant;
use App\Services\Orders\BuyerOrderWorkflow;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class SupplyPlanningCalculationService
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
        $productId = (int) ($attributes['product_id'] ?? 0);
        $product = Product::query()->whereKey($productId)->where('status', 'active')->first();
        if ($product === null) {
            throw ValidationException::withMessages(['product_id' => 'The selected product must exist and be active.']);
        }

        $variantId = $this->nullableId($attributes['product_variant_id'] ?? null);
        $variant = null;
        if ($variantId !== null) {
            $variant = ProductVariant::query()
                ->whereKey($variantId)
                ->where('product_id', $productId)
                ->where('status', 'active')
                ->first();
            if ($variant === null) {
                throw ValidationException::withMessages(['product_variant_id' => 'The selected Product Variant must belong to the selected active product.']);
            }
        }

        $period = $this->periodService->normalize($attributes);
        $confirmedQuantity = $this->confirmedOrderQuantity($productId, $variantId, $period);
        $forecastQuantity = $this->forecastQuantity($productId, $variantId, $period);
        $requiredQuantity = $confirmedQuantity + $forecastQuantity;
        $availableQuantity = $this->nullableNumber($attributes['available_quantity'] ?? null);
        $plannedProductionQuantity = $availableQuantity === null
            ? $requiredQuantity
            : max($requiredQuantity - $availableQuantity, 0);

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
            ...$period,
            'confirmed_order_quantity' => round($confirmedQuantity, 4),
            'forecast_quantity' => round($forecastQuantity, 4),
            'required_quantity' => round($requiredQuantity, 4),
            'available_quantity' => $availableQuantity === null ? null : round($availableQuantity, 4),
            'planned_production_quantity' => round($plannedProductionQuantity, 4),
            'status' => $availableQuantity === null ? 'pending_inventory' : 'calculated',
            'firm_order_statuses' => $this->workflow->firmDemandStatuses(),
        ];
    }

    /**
     * @param  array{period_type: string, period_start: string, period_end: string}  $period
     */
    private function confirmedOrderQuantity(int $productId, ?int $variantId, array $period): float
    {
        $query = DB::table('buyer_order_items')
            ->join('buyer_orders', 'buyer_orders.id', '=', 'buyer_order_items.buyer_order_id')
            ->where('buyer_order_items.product_id', $productId)
            ->whereIn('buyer_orders.status', $this->workflow->firmDemandStatuses())
            ->whereBetween('buyer_orders.delivery_date', [$period['period_start'], $period['period_end']])
            ->whereNull('buyer_orders.deleted_at');

        if ($variantId !== null) {
            $query->where('buyer_order_items.product_variant_id', $variantId);
        }

        return round((float) $query->sum('buyer_order_items.quantity'), 4);
    }

    /**
     * @param  array{period_type: string, period_start: string, period_end: string}  $period
     */
    private function forecastQuantity(int $productId, ?int $variantId, array $period): float
    {
        $query = DB::table('demand_forecasts')
            ->where('product_id', $productId)
            ->where('period_type', $period['period_type'])
            ->where('period_start', $period['period_start'])
            ->where('status', 'active')
            ->whereNull('deleted_at');

        if ($variantId !== null) {
            $query->where('product_variant_id', $variantId);
        }

        return round((float) $query->sum('forecast_quantity'), 4);

    }

    private function nullableId(mixed $value): ?int
    {
        return $value === null || $value === '' ? null : (int) $value;
    }

    private function nullableNumber(mixed $value): ?float
    {
        return $value === null || $value === '' ? null : (float) $value;
    }
}
