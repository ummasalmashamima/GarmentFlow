<?php

declare(strict_types=1);

namespace App\Resources\Planning;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class MaterialRequirementResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'mrp_run_id' => $this->mrp_run_id,
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
            'gross_quantity' => (string) $this->gross_quantity,
            'available_quantity' => $this->available_quantity === null ? null : (string) $this->available_quantity,
            'allocated_quantity' => $this->allocated_quantity === null ? null : (string) $this->allocated_quantity,
            'net_quantity' => $this->net_quantity === null ? null : (string) $this->net_quantity,
            'status' => $this->status,
            'sources' => MaterialRequirementSourceResource::collection($this->whenLoaded('sources')),
            'notes' => $this->notes,
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
