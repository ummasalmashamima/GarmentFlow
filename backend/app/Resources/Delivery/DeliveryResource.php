<?php

declare(strict_types=1);

namespace App\Resources\Delivery;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class DeliveryResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'delivery_number' => $this->delivery_number,
            'sales_order_id' => $this->sales_order_id,
            'sales_order' => $this->whenLoaded('salesOrder', fn () => [
                'id' => $this->salesOrder->id,
                'sales_order_number' => $this->salesOrder->sales_order_number,
                'status' => $this->salesOrder->status,
                'buyer' => $this->salesOrder->relationLoaded('buyer') && $this->salesOrder->buyer ? [
                    'id' => $this->salesOrder->buyer->id,
                    'code' => $this->salesOrder->buyer->code,
                    'name' => $this->salesOrder->buyer->name,
                ] : null,
                'customer' => $this->salesOrder->relationLoaded('customer') && $this->salesOrder->customer ? [
                    'id' => $this->salesOrder->customer->id,
                    'code' => $this->salesOrder->customer->code,
                    'name' => $this->salesOrder->customer->name,
                ] : null,
                'confirmed_quantity' => $this->salesOrder->confirmed_quantity,
                'delivered_quantity' => $this->salesOrder->delivered_quantity,
                'remaining_quantity' => $this->salesOrder->remaining_quantity,
            ]),
            'warehouse_id' => $this->warehouse_id,
            'warehouse' => $this->whenLoaded('warehouse', fn () => [
                'id' => $this->warehouse->id,
                'code' => $this->warehouse->code,
                'name' => $this->warehouse->name,
            ]),
            'status' => $this->status,
            'delivery_date' => $this->delivery_date?->format('Y-m-d'),
            'expected_delivery_date' => $this->expected_delivery_date?->format('Y-m-d'),
            'dispatched_at' => $this->dispatched_at?->toISOString(),
            'delivered_at' => $this->delivered_at?->toISOString(),
            'ordered_quantity' => $this->ordered_quantity,
            'dispatched_quantity' => $this->dispatched_quantity,
            'delivered_quantity' => $this->delivered_quantity,
            'remaining_quantity' => $this->remaining_quantity,
            'carrier_name' => $this->carrier_name,
            'tracking_number' => $this->tracking_number,
            'delivery_address' => $this->delivery_address,
            'contact_information' => $this->contact_information,
            'remarks' => $this->remarks,
            'creator' => $this->whenLoaded('creator', fn () => [
                'id' => $this->creator->id,
                'name' => $this->creator->name,
                'email' => $this->creator->email,
            ]),
            'items' => DeliveryItemResource::collection($this->whenLoaded('items')),
            'tracking_history' => DeliveryTrackingHistoryResource::collection($this->whenLoaded('trackingHistories')),
        ];
    }
}
