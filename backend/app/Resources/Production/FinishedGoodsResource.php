<?php

declare(strict_types=1);

namespace App\Resources\Production;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class FinishedGoodsResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'finished_goods_number' => $this->finished_goods_number,
            'production_order' => $this->whenLoaded('productionOrder', fn (): array => [
                'id' => $this->productionOrder->id,
                'order_number' => $this->productionOrder->order_number,
                'status' => $this->productionOrder->status,
            ]),
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
            'unit' => $this->whenLoaded('unit', fn (): array => [
                'id' => $this->unit->id,
                'code' => $this->unit->code,
                'name' => $this->unit->name,
                'symbol' => $this->unit->symbol,
            ]),
            'quantity' => (string) $this->quantity,
            'warehouse' => $this->whenLoaded('warehouse', fn (): array => [
                'id' => $this->warehouse->id,
                'code' => $this->warehouse->code,
                'name' => $this->warehouse->name,
            ]),
            'warehouse_location' => $this->whenLoaded('warehouseLocation', fn (): ?array => $this->warehouseLocation === null ? null : [
                'id' => $this->warehouseLocation->id,
                'code' => $this->warehouseLocation->code,
                'name' => $this->warehouseLocation->name,
            ]),
            'inventory_transaction' => $this->whenLoaded('inventoryTransaction', fn (): ?array => $this->inventoryTransaction === null ? null : [
                'id' => $this->inventoryTransaction->id,
                'transaction_number' => $this->inventoryTransaction->transaction_number,
                'transaction_type' => $this->inventoryTransaction->transaction_type,
            ]),
            'idempotency_key' => $this->idempotency_key,
            'finished_date' => $this->finished_date?->toDateString(),
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
