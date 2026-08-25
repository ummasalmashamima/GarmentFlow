<?php

declare(strict_types=1);

namespace App\Resources\Procurement;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PurchaseOrderItemResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'purchase_requisition_item_id' => $this->purchase_requisition_item_id,
            'material_id' => $this->material_id,
            'unit_id' => $this->unit_id,
            'quantity' => $this->quantity,
            'unit_price' => $this->unit_price,
            'line_total' => $this->line_total,
            'received_quantity' => $this->received_quantity,
            'remaining_quantity' => $this->remainingQuantity(),
            'line_number' => $this->line_number,
            'remarks' => $this->remarks,
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
            'purchase_requisition_item' => $this->whenLoaded('purchaseRequisitionItem', fn (): ?array => $this->purchaseRequisitionItem === null ? null : [
                'id' => $this->purchaseRequisitionItem->id,
                'requisition_number' => $this->purchaseRequisitionItem->requisition?->requisition_number,
            ]),
        ];
    }
}
