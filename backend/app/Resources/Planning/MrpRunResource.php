<?php

declare(strict_types=1);

namespace App\Resources\Planning;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class MrpRunResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'run_number' => $this->run_number,
            'status' => $this->status,
            'planning_date' => $this->planning_date?->toDateString(),
            'total_gross_quantity' => (string) $this->total_gross_quantity,
            'total_net_quantity' => $this->total_net_quantity === null ? null : (string) $this->total_net_quantity,
            'inventory_data_available' => (bool) $this->inventory_data_available,
            'calculated_at' => $this->calculated_at?->toISOString(),
            'creator' => $this->whenLoaded('creator', fn (): ?array => $this->creator === null ? null : [
                'id' => $this->creator->id,
                'name' => $this->creator->name,
                'email' => $this->creator->email,
            ]),
            'material_requirements' => MaterialRequirementResource::collection($this->whenLoaded('materialRequirements')),
            'material_requirement_count' => $this->when(isset($this->material_requirements_count), $this->material_requirements_count),
            'notes' => $this->notes,
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
