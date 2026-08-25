<?php

declare(strict_types=1);

namespace App\Resources\Delivery;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class DeliveryItemResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'delivery_id' => $this->delivery_id,
            'sales_order_item_id' => $this->sales_order_item_id,
            'line_number' => $this->line_number,
            'product_id' => $this->product_id,
            'product_variant_id' => $this->product_variant_id,
            'unit_id' => $this->unit_id,
            'product' => $this->whenLoaded('product', fn () => [
                'id' => $this->product->id,
                'code' => $this->product->code,
                'name' => $this->product->name,
            ]),
            'product_variant' => $this->whenLoaded('productVariant', fn () => [
                'id' => $this->productVariant->id,
                'sku' => $this->productVariant->sku,
                'variant_name' => $this->productVariant->variant_name,
            ]),
            'unit' => $this->whenLoaded('unit', fn () => [
                'id' => $this->unit->id,
                'code' => $this->unit->code,
                'name' => $this->unit->name,
                'symbol' => $this->unit->symbol,
            ]),
            'sales_order_item' => $this->whenLoaded('salesOrderItem', fn () => [
                'id' => $this->salesOrderItem->id,
                'ordered_quantity' => $this->salesOrderItem->ordered_quantity,
                'confirmed_quantity' => $this->salesOrderItem->confirmed_quantity,
                'delivered_quantity' => $this->salesOrderItem->delivered_quantity,
                'remaining_quantity' => $this->salesOrderItem->remaining_quantity,
            ]),
            'delivery_quantity' => $this->delivery_quantity,
            'dispatched_quantity' => $this->dispatched_quantity,
            'delivered_quantity' => $this->delivered_quantity,
            'remaining_quantity' => $this->remaining_quantity,
            'inventory_transaction_id' => $this->inventory_transaction_id,
            'inventory_transaction' => $this->whenLoaded('inventoryTransaction', fn () => $this->inventoryTransaction ? [
                'id' => $this->inventoryTransaction->id,
                'transaction_number' => $this->inventoryTransaction->transaction_number,
                'transaction_type' => $this->inventoryTransaction->transaction_type,
                'quantity' => $this->inventoryTransaction->quantity,
                'idempotency_key' => $this->inventoryTransaction->idempotency_key,
            ] : null),
            'remarks' => $this->remarks,
        ];
    }
}
