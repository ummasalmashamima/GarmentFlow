<?php

declare(strict_types=1);

namespace App\Resources\Procurement;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PurchaseRequisitionItemResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'material_id' => $this->material_id,
            'unit_id' => $this->unit_id,
            'material_requirement_id' => $this->material_requirement_id,
            'quantity' => $this->quantity,
            'converted_quantity' => $this->converted_quantity,
            'remaining_quantity' => $this->remainingQuantity(),
            'line_number' => $this->line_number,
            'remarks' => $this->remarks,
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
        ];
    }
}
