<?php

declare(strict_types=1);

namespace App\Resources\Inventory;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class InventoryTransactionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'transaction_number' => $this->transaction_number,
            'inventory_balance_id' => $this->inventory_balance_id,
            'warehouse_id' => $this->warehouse_id,
            'warehouse_location_id' => $this->warehouse_location_id,
            'material_id' => $this->material_id,
            'product_id' => $this->product_id,
            'product_variant_id' => $this->product_variant_id,
            'unit_id' => $this->unit_id,
            'quantity' => (string) $this->quantity,
            'transaction_type' => $this->transaction_type,
            'reference_type' => $this->reference_type,
            'reference_id' => $this->reference_id,
            'performed_by' => $this->performed_by,
            'transaction_date' => $this->transaction_date?->toISOString(),
            'idempotency_key' => $this->idempotency_key,
            'remarks' => $this->remarks,
            'inventory_balance' => $this->whenLoaded('inventoryBalance', fn (): ?array => $this->inventoryBalance === null ? null : [
                'id' => $this->inventoryBalance->id,
                'stock_key' => $this->inventoryBalance->stock_key,
                'quantity_on_hand' => (string) $this->inventoryBalance->quantity_on_hand,
                'quantity_reserved' => (string) $this->inventoryBalance->quantity_reserved,
                'quantity_available' => number_format($this->inventoryBalance->available_quantity, 4, '.', ''),
            ]),
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
            'performer' => $this->whenLoaded('performer', fn (): ?array => $this->performer === null ? null : [
                'id' => $this->performer->id,
                'name' => $this->performer->name,
                'email' => $this->performer->email,
            ]),
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
