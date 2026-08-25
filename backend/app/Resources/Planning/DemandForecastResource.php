<?php

declare(strict_types=1);

namespace App\Resources\Planning;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class DemandForecastResource extends JsonResource
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
            'forecast_quantity' => (string) $this->forecast_quantity,
            'method' => $this->method,
            'status' => $this->status,
            'forecast_date' => $this->forecast_date?->toDateString(),
            'confidence_score' => $this->confidence_score === null ? null : (string) $this->confidence_score,
            'accuracy_score' => $this->accuracy_score === null ? null : (string) $this->accuracy_score,
            'lookback_periods' => $this->lookback_periods,
            'calculation_snapshot' => $this->calculation_snapshot,
            'creator' => $this->whenLoaded('creator', fn (): ?array => $this->creator === null ? null : [
                'id' => $this->creator->id,
                'name' => $this->creator->name,
                'email' => $this->creator->email,
            ]),
            'notes' => $this->notes,
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
