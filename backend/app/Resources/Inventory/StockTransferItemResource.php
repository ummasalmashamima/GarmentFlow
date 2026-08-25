<?php

declare(strict_types=1);

namespace App\Resources\Inventory;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class StockTransferItemResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'stock_transfer_id' => $this->stock_transfer_id,
            'source_inventory_balance_id' => $this->source_inventory_balance_id,
            'destination_inventory_balance_id' => $this->destination_inventory_balance_id,
            'material_id' => $this->material_id,
            'product_id' => $this->product_id,
            'product_variant_id' => $this->product_variant_id,
            'unit_id' => $this->unit_id,
            'quantity' => (string) $this->quantity,
            'line_number' => $this->line_number,
            'remarks' => $this->remarks,
            'source_balance' => $this->whenLoaded('sourceBalance', fn (): ?array => $this->sourceBalance === null ? null : [
                'id' => $this->sourceBalance->id,
                'stock_key' => $this->sourceBalance->stock_key,
                'quantity_on_hand' => (string) $this->sourceBalance->quantity_on_hand,
                'quantity_available' => number_format($this->sourceBalance->available_quantity, 4, '.', ''),
            ]),
            'destination_balance' => $this->whenLoaded('destinationBalance', fn (): ?array => $this->destinationBalance === null ? null : [
                'id' => $this->destinationBalance->id,
                'stock_key' => $this->destinationBalance->stock_key,
                'quantity_on_hand' => (string) $this->destinationBalance->quantity_on_hand,
                'quantity_available' => number_format($this->destinationBalance->available_quantity, 4, '.', ''),
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
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
