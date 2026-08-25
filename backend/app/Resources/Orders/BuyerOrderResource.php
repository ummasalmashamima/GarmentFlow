<?php

declare(strict_types=1);

namespace App\Resources\Orders;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class BuyerOrderResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'order_number' => $this->order_number,
            'buyer_id' => $this->buyer_id,
            'order_date' => $this->order_date?->format('Y-m-d'),
            'delivery_date' => $this->delivery_date?->format('Y-m-d'),
            'status' => $this->status,
            'total_quantity' => $this->total_quantity,
            'total_amount' => $this->total_amount,
            'remarks' => $this->remarks,
            'buyer' => $this->whenLoaded('buyer', fn (): array => [
                'id' => $this->buyer->id,
                'code' => $this->buyer->code,
                'name' => $this->buyer->name,
            ]),
            'creator' => $this->whenLoaded('creator', fn (): array => [
                'id' => $this->creator->id,
                'name' => $this->creator->name,
                'email' => $this->creator->email,
            ]),
            'items' => BuyerOrderItemResource::collection($this->whenLoaded('items')),
            'approvals' => OrderApprovalResource::collection($this->whenLoaded('approvals')),
            'latest_approval' => new OrderApprovalResource($this->whenLoaded('latestApproval')),
            'planning_input' => new OrderPlanningInputResource($this->whenLoaded('planningInput')),
            'status_history' => OrderStatusHistoryResource::collection($this->whenLoaded('statusHistories')),
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
