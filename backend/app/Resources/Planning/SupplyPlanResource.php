<?php

declare(strict_types=1);

namespace App\Resources\Planning;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SupplyPlanResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
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
            'period_type' => $this->period_type,
            'period_start' => $this->period_start?->toDateString(),
            'period_end' => $this->period_end?->toDateString(),
            'confirmed_order_quantity' => (string) $this->confirmed_order_quantity,
            'forecast_quantity' => (string) $this->forecast_quantity,
            'required_quantity' => (string) $this->required_quantity,
            'available_quantity' => $this->available_quantity === null ? null : (string) $this->available_quantity,
            'planned_production_quantity' => (string) $this->planned_production_quantity,
            'status' => $this->status,
            'creator' => $this->whenLoaded('creator', fn (): ?array => $this->creator === null ? null : [
                'id' => $this->creator->id,
                'name' => $this->creator->name,
                'email' => $this->creator->email,
            ]),
            'material_requirement_source_count' => $this->when(isset($this->material_requirement_source_count), $this->material_requirement_source_count),
            'notes' => $this->notes,
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
