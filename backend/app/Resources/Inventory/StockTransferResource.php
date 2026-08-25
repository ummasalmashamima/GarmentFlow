<?php

declare(strict_types=1);

namespace App\Resources\Inventory;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class StockTransferResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'transfer_number' => $this->transfer_number,
            'source_warehouse_id' => $this->source_warehouse_id,
            'source_location_id' => $this->source_location_id,
            'destination_warehouse_id' => $this->destination_warehouse_id,
            'destination_location_id' => $this->destination_location_id,
            'transferred_by' => $this->transferred_by,
            'transfer_date' => $this->transfer_date?->toISOString(),
            'status' => $this->status,
            'remarks' => $this->remarks,
            'source_warehouse' => $this->whenLoaded('sourceWarehouse', fn (): ?array => $this->sourceWarehouse === null ? null : [
                'id' => $this->sourceWarehouse->id,
                'code' => $this->sourceWarehouse->code,
                'name' => $this->sourceWarehouse->name,
            ]),
            'source_location' => $this->whenLoaded('sourceLocation', fn (): ?array => $this->sourceLocation === null ? null : [
                'id' => $this->sourceLocation->id,
                'code' => $this->sourceLocation->code,
                'name' => $this->sourceLocation->name,
            ]),
            'destination_warehouse' => $this->whenLoaded('destinationWarehouse', fn (): ?array => $this->destinationWarehouse === null ? null : [
                'id' => $this->destinationWarehouse->id,
                'code' => $this->destinationWarehouse->code,
                'name' => $this->destinationWarehouse->name,
            ]),
            'destination_location' => $this->whenLoaded('destinationLocation', fn (): ?array => $this->destinationLocation === null ? null : [
                'id' => $this->destinationLocation->id,
                'code' => $this->destinationLocation->code,
                'name' => $this->destinationLocation->name,
            ]),
            'transferor' => $this->whenLoaded('transferor', fn (): ?array => $this->transferor === null ? null : [
                'id' => $this->transferor->id,
                'name' => $this->transferor->name,
                'email' => $this->transferor->email,
            ]),
            'items' => StockTransferItemResource::collection($this->whenLoaded('items')),
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
