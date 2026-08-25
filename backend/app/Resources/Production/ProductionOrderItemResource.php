<?php

declare(strict_types=1);

namespace App\Resources\Production;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ProductionOrderItemResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'bom_item_id' => $this->bom_item_id,
            'material' => $this->whenLoaded('material', fn (): array => [
                'id' => $this->material->id,
                'code' => $this->material->code,
                'name' => $this->material->name,
            ]),
            'unit' => $this->whenLoaded('unit', fn (): array => [
                'id' => $this->unit->id,
                'code' => $this->unit->code,
                'name' => $this->unit->name,
                'symbol' => $this->unit->symbol,
            ]),
            'bom_quantity' => (string) $this->bom_quantity,
            'wastage_percentage' => (string) $this->wastage_percentage,
            'required_quantity' => (string) $this->required_quantity,
            'consumed_quantity' => (string) $this->consumed_quantity,
            'remaining_quantity' => number_format($this->remaining_quantity, 4, '.', ''),
            'remarks' => $this->remarks,
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
