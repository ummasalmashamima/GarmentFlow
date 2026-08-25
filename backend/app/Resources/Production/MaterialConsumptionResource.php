<?php

declare(strict_types=1);

namespace App\Resources\Production;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class MaterialConsumptionResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'consumption_number' => $this->consumption_number,
            'production_order' => $this->whenLoaded('productionOrder', fn (): array => [
                'id' => $this->productionOrder->id,
                'order_number' => $this->productionOrder->order_number,
            ]),
            'production_order_item' => $this->whenLoaded('productionOrderItem', fn (): array => [
                'id' => $this->productionOrderItem->id,
                'required_quantity' => (string) $this->productionOrderItem->required_quantity,
                'consumed_quantity' => (string) $this->productionOrderItem->consumed_quantity,
            ]),
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
            'quantity' => (string) $this->quantity,
            'inventory_transaction' => $this->whenLoaded('inventoryTransaction', fn (): ?array => $this->inventoryTransaction === null ? null : [
                'id' => $this->inventoryTransaction->id,
                'transaction_number' => $this->inventoryTransaction->transaction_number,
                'transaction_type' => $this->inventoryTransaction->transaction_type,
            ]),
            'idempotency_key' => $this->idempotency_key,
            'consumption_date' => $this->consumption_date?->toDateString(),
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
