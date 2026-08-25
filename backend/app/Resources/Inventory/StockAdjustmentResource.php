<?php

declare(strict_types=1);

namespace App\Resources\Inventory;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class StockAdjustmentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'adjustment_number' => $this->adjustment_number,
            'direction' => $this->direction,
            'warehouse_id' => $this->warehouse_id,
            'warehouse_location_id' => $this->warehouse_location_id,
            'adjusted_by' => $this->adjusted_by,
            'adjustment_date' => $this->adjustment_date?->toISOString(),
            'status' => $this->status,
            'reason' => $this->reason,
            'remarks' => $this->remarks,
            'warehouse' => $this->whenLoaded('warehouse', fn (): ?array => $this->warehouse === null ? null : [
                'id' => $this->warehouse->id,
                'code' => $this->warehouse->code,
                'name' => $this->warehouse->name,
            ]),
            'warehouse_location' => $this->whenLoaded('warehouseLocation', fn (): ?array => $this->warehouseLocation === null ? null : [
                'id' => $this->warehouseLocation->id,
                'code' => $this->warehouseLocation->code,
                'name' => $this->warehouseLocation->name,
            ]),
            'adjuster' => $this->whenLoaded('adjuster', fn (): ?array => $this->adjuster === null ? null : [
                'id' => $this->adjuster->id,
                'name' => $this->adjuster->name,
                'email' => $this->adjuster->email,
            ]),
            'items' => StockAdjustmentItemResource::collection($this->whenLoaded('items')),
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
