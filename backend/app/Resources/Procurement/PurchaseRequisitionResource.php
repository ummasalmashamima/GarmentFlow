<?php

declare(strict_types=1);

namespace App\Resources\Procurement;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PurchaseRequisitionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'requisition_number' => $this->requisition_number,
            'request_date' => $this->request_date?->format('Y-m-d'),
            'required_date' => $this->required_date?->format('Y-m-d'),
            'priority' => $this->priority,
            'source' => $this->source,
            'department_id' => $this->department_id,
            'status' => $this->status,
            'remarks' => $this->remarks,
            'requester' => $this->whenLoaded('requester', fn (): array => [
                'id' => $this->requester->id,
                'name' => $this->requester->name,
                'email' => $this->requester->email,
            ]),
            'department' => $this->whenLoaded('department', fn (): ?array => $this->department === null ? null : [
                'id' => $this->department->id,
                'name' => $this->department->name,
            ]),
            'items' => PurchaseRequisitionItemResource::collection($this->whenLoaded('items')),
            'approvals' => PurchaseApprovalResource::collection($this->whenLoaded('approvals')),
            'status_history' => ProcurementStatusHistoryResource::collection($this->whenLoaded('statusHistories')),
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
