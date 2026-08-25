<?php

declare(strict_types=1);

namespace App\Resources\Finance;

use App\Services\Finance\InvoiceWorkflow;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class InvoiceResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $effectiveStatus = $this->status;
        if (in_array($this->status, [InvoiceWorkflow::ISSUED, InvoiceWorkflow::PARTIALLY_PAID], true)
            && (float) $this->due_amount > 0
            && $this->due_date?->isPast()) {
            $effectiveStatus = InvoiceWorkflow::OVERDUE;
        }

        return [
            'id' => $this->id,
            'invoice_number' => $this->invoice_number,
            'sales_order_id' => $this->sales_order_id,
            'sales_order' => $this->whenLoaded('salesOrder', fn () => [
                'id' => $this->salesOrder->id,
                'sales_order_number' => $this->salesOrder->sales_order_number,
                'status' => $this->salesOrder->status,
            ]),
            'buyer_id' => $this->buyer_id,
            'customer_id' => $this->customer_id,
            'buyer' => $this->whenLoaded('buyer', fn () => ['id' => $this->buyer->id, 'code' => $this->buyer->code, 'name' => $this->buyer->name]),
            'customer' => $this->whenLoaded('customer', fn () => ['id' => $this->customer->id, 'code' => $this->customer->code, 'name' => $this->customer->name]),
            'warehouse_id' => $this->warehouse_id,
            'warehouse' => $this->whenLoaded('warehouse', fn () => ['id' => $this->warehouse->id, 'code' => $this->warehouse->code, 'name' => $this->warehouse->name]),
            'invoice_date' => $this->invoice_date?->toDateString(),
            'due_date' => $this->due_date?->toDateString(),
            'status' => $effectiveStatus,
            'stored_status' => $this->status,
            'subtotal' => $this->subtotal,
            'discount_amount' => $this->discount_amount,
            'tax_amount' => $this->tax_amount,
            'total_amount' => $this->total_amount,
            'paid_amount' => $this->paid_amount,
            'due_amount' => $this->due_amount,
            'issued_at' => $this->issued_at,
            'remarks' => $this->remarks,
            'created_by' => $this->created_by,
            'creator' => $this->whenLoaded('creator', fn () => ['id' => $this->creator->id, 'name' => $this->creator->name, 'email' => $this->creator->email]),
            'items' => InvoiceItemResource::collection($this->whenLoaded('items')),
            'payments' => PaymentResource::collection($this->whenLoaded('payments')),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
