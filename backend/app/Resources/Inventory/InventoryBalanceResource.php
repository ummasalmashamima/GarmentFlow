<?php

declare(strict_types=1);

namespace App\Resources\Inventory;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class InventoryBalanceResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'stock_key' => $this->stock_key,
            'warehouse_id' => $this->warehouse_id,
            'warehouse_location_id' => $this->warehouse_location_id,
            'item_type' => $this->item_type,
            'material_id' => $this->material_id,
            'product_id' => $this->product_id,
            'product_variant_id' => $this->product_variant_id,
            'unit_id' => $this->unit_id,
            'quantity_on_hand' => (string) $this->quantity_on_hand,
            'quantity_reserved' => (string) $this->quantity_reserved,
            'quantity_available' => number_format($this->available_quantity, 4, '.', ''),
            'status' => $this->status,
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
            'material' => $this->whenLoaded('material', fn (): ?array => $this->material === null ? null : [
                'id' => $this->material->id,
                'code' => $this->material->code,
                'name' => $this->material->name,
            ]),
            'product' => $this->whenLoaded('product', fn (): ?array => $this->product === null ? null : [
                'id' => $this->product->id,
                'code' => $this->product->code,
                'name' => $this->product->name,
            ]),
            'product_variant' => $this->whenLoaded('productVariant', fn (): ?array => $this->productVariant === null ? null : [
                'id' => $this->productVariant->id,
                'sku' => $this->productVariant->sku,
                'variant_name' => $this->productVariant->variant_name,
            ]),
            'unit' => $this->whenLoaded('unit', fn (): ?array => $this->unit === null ? null : [
                'id' => $this->unit->id,
                'code' => $this->unit->code,
                'name' => $this->unit->name,
            ]),
            'transactions' => InventoryTransactionResource::collection($this->whenLoaded('transactions')),
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
