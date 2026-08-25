<?php

declare(strict_types=1);

namespace App\Resources\Procurement;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PurchaseOrderResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'purchase_order_number' => $this->purchase_order_number,
            'supplier_id' => $this->supplier_id,
            'po_date' => $this->po_date?->format('Y-m-d'),
            'expected_delivery_date' => $this->expected_delivery_date?->format('Y-m-d'),
            'currency' => $this->currency,
            'payment_terms' => $this->payment_terms,
            'shipping_terms' => $this->shipping_terms,
            'subtotal' => $this->subtotal,
            'tax_total' => $this->tax_total,
            'discount_total' => $this->discount_total,
            'total_amount' => $this->total_amount,
            'status' => $this->status,
            'remarks' => $this->remarks,
            'supplier' => $this->whenLoaded('supplier', fn (): array => [
                'id' => $this->supplier->id,
                'code' => $this->supplier->code,
                'name' => $this->supplier->name,
            ]),
            'creator' => $this->whenLoaded('creator', fn (): array => [
                'id' => $this->creator->id,
                'name' => $this->creator->name,
                'email' => $this->creator->email,
            ]),
            'items' => PurchaseOrderItemResource::collection($this->whenLoaded('items')),
            'approvals' => PurchaseApprovalResource::collection($this->whenLoaded('approvals')),
            'goods_receipts' => GoodsReceiptResource::collection($this->whenLoaded('goodsReceipts')),
            'status_history' => ProcurementStatusHistoryResource::collection($this->whenLoaded('statusHistories')),
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
