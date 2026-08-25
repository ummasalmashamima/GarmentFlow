<?php

declare(strict_types=1);

namespace App\Resources\Sales;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SalesOrderResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'sales_order_number' => $this->sales_order_number,
            'buyer_id' => $this->buyer_id,
            'customer_id' => $this->customer_id,
            'buyer' => $this->whenLoaded('buyer', fn () => [
                'id' => $this->buyer->id,
                'code' => $this->buyer->code,
                'name' => $this->buyer->name,
                'contact_name' => $this->buyer->contact_name,
                'email' => $this->buyer->email,
                'phone' => $this->buyer->phone,
                'address' => $this->buyer->address,
            ]),
            'customer' => $this->whenLoaded('customer', fn () => [
                'id' => $this->customer->id,
                'code' => $this->customer->code,
                'name' => $this->customer->name,
                'contact_name' => $this->customer->contact_name,
                'email' => $this->customer->email,
                'phone' => $this->customer->phone,
                'address' => $this->customer->address,
            ]),
            'warehouse_id' => $this->warehouse_id,
            'warehouse' => $this->whenLoaded('warehouse', fn () => [
                'id' => $this->warehouse->id,
                'code' => $this->warehouse->code,
                'name' => $this->warehouse->name,
                'address' => $this->warehouse->address,
            ]),
            'order_date' => $this->order_date?->format('Y-m-d'),
            'required_delivery_date' => $this->required_delivery_date?->format('Y-m-d'),
            'delivery_address' => $this->delivery_address,
            'contact_information' => $this->contact_information,
            'status' => $this->status,
            'subtotal' => $this->subtotal,
            'order_discount_amount' => $this->order_discount_amount,
            'order_tax_amount' => $this->order_tax_amount,
            'discount_amount' => $this->discount_amount,
            'tax_amount' => $this->tax_amount,
            'total_amount' => $this->total_amount,
            'ordered_quantity' => $this->ordered_quantity,
            'confirmed_quantity' => $this->confirmed_quantity,
            'delivered_quantity' => $this->delivered_quantity,
            'remaining_quantity' => $this->remaining_quantity,
            'confirmed_at' => $this->confirmed_at?->toISOString(),
            'remarks' => $this->remarks,
            'created_by' => $this->created_by,
            'creator' => $this->whenLoaded('creator', fn () => [
                'id' => $this->creator->id,
                'name' => $this->creator->name,
                'email' => $this->creator->email,
            ]),
            'items' => SalesOrderItemResource::collection($this->whenLoaded('items')),
            'status_history' => SalesOrderStatusHistoryResource::collection($this->whenLoaded('statusHistories')),
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
