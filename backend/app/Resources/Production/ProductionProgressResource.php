<?php

declare(strict_types=1);

namespace App\Resources\Production;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ProductionProgressResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'production_order' => $this->whenLoaded('productionOrder', fn (): array => [
                'id' => $this->productionOrder->id,
                'order_number' => $this->productionOrder->order_number,
            ]),
            'planned_quantity' => (string) $this->planned_quantity,
            'completed_quantity' => (string) $this->completed_quantity,
            'rejected_quantity' => (string) $this->rejected_quantity,
            'remaining_quantity' => (string) $this->remaining_quantity,
            'progress_percentage' => (string) $this->progress_percentage,
            'production_date' => $this->production_date?->toDateString(),
            'recorder' => $this->whenLoaded('recorder', fn (): ?array => $this->recorder === null ? null : [
                'id' => $this->recorder->id,
                'name' => $this->recorder->name,
            ]),
            'remarks' => $this->remarks,
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
