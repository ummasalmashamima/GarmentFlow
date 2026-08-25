<?php

declare(strict_types=1);

namespace App\Resources\Production;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ProductionPlanResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'plan_number' => $this->plan_number,
            'product' => $this->whenLoaded('product', fn (): array => [
                'id' => $this->product->id,
                'code' => $this->product->code,
                'name' => $this->product->name,
            ]),
            'product_variant' => $this->whenLoaded('productVariant', fn (): ?array => $this->productVariant === null ? null : [
                'id' => $this->productVariant->id,
                'code' => $this->productVariant->sku,
                'name' => $this->productVariant->variant_name,
            ]),
            'supply_plan' => $this->whenLoaded('supplyPlan', fn (): ?array => $this->supplyPlan === null ? null : [
                'id' => $this->supplyPlan->id,
                'period_type' => $this->supplyPlan->period_type,
                'period_start' => $this->supplyPlan->period_start?->toDateString(),
                'period_end' => $this->supplyPlan->period_end?->toDateString(),
                'planned_production_quantity' => (string) $this->supplyPlan->planned_production_quantity,
            ]),
            'buyer_order' => $this->whenLoaded('buyerOrder', fn (): ?array => $this->buyerOrder === null ? null : [
                'id' => $this->buyerOrder->id,
                'order_number' => $this->buyerOrder->order_number,
                'status' => $this->buyerOrder->status,
            ]),
            'planned_quantity' => (string) $this->planned_quantity,
            'planned_start_date' => $this->planned_start_date?->toDateString(),
            'planned_end_date' => $this->planned_end_date?->toDateString(),
            'priority' => $this->priority,
            'status' => $this->status,
            'creator' => $this->whenLoaded('creator', fn (): ?array => $this->creator === null ? null : [
                'id' => $this->creator->id,
                'name' => $this->creator->name,
                'email' => $this->creator->email,
            ]),
            'production_orders' => $this->whenLoaded('productionOrders', fn (): array => $this->productionOrders->map(fn ($order): array => [
                'id' => $order->id,
                'order_number' => $order->order_number,
                'status' => $order->status,
                'planned_quantity' => (string) $order->planned_quantity,
            ])->values()->all()),
            'remarks' => $this->remarks,
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
