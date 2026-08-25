<?php

declare(strict_types=1);

namespace App\Resources\Procurement;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class GoodsReceiptResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'receipt_number' => $this->receipt_number,
            'purchase_order_id' => $this->purchase_order_id,
            'supplier_id' => $this->supplier_id,
            'warehouse_id' => $this->warehouse_id,
            'warehouse_location_id' => $this->warehouse_location_id,
            'receipt_date' => $this->receipt_date?->format('Y-m-d'),
            'status' => $this->status,
            'posted_at' => $this->posted_at?->toISOString(),
            'remarks' => $this->remarks,
            'purchase_order' => $this->whenLoaded('purchaseOrder', fn (): ?array => $this->purchaseOrder === null ? null : [
                'id' => $this->purchaseOrder->id,
                'purchase_order_number' => $this->purchaseOrder->purchase_order_number,
                'status' => $this->purchaseOrder->status,
            ]),
            'supplier' => $this->whenLoaded('supplier', fn (): array => [
                'id' => $this->supplier->id,
                'code' => $this->supplier->code,
                'name' => $this->supplier->name,
            ]),
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
            'receiver' => $this->whenLoaded('receiver', fn (): array => [
                'id' => $this->receiver->id,
                'name' => $this->receiver->name,
                'email' => $this->receiver->email,
            ]),
            'items' => GoodsReceiptItemResource::collection($this->whenLoaded('items')),
            'status_history' => ProcurementStatusHistoryResource::collection($this->whenLoaded('statusHistories')),
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
