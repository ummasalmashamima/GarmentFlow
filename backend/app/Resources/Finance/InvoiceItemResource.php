<?php

declare(strict_types=1);

namespace App\Resources\Finance;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class InvoiceItemResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'invoice_id' => $this->invoice_id,
            'sales_order_item_id' => $this->sales_order_item_id,
            'line_number' => $this->line_number,
            'product_id' => $this->product_id,
            'product_variant_id' => $this->product_variant_id,
            'unit_id' => $this->unit_id,
            'product' => $this->whenLoaded('product', fn () => ['id' => $this->product->id, 'code' => $this->product->code, 'name' => $this->product->name]),
            'product_variant' => $this->whenLoaded('productVariant', fn () => ['id' => $this->productVariant->id, 'sku' => $this->productVariant->sku, 'variant_name' => $this->productVariant->variant_name]),
            'unit' => $this->whenLoaded('unit', fn () => ['id' => $this->unit->id, 'code' => $this->unit->code, 'name' => $this->unit->name, 'symbol' => $this->unit->symbol]),
            'quantity' => $this->quantity,
            'unit_price' => $this->unit_price,
            'discount_amount' => $this->discount_amount,
            'tax_amount' => $this->tax_amount,
            'line_total' => $this->line_total,
            'remarks' => $this->remarks,
        ];
    }
}
