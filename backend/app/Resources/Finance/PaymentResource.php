<?php

declare(strict_types=1);

namespace App\Resources\Finance;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PaymentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'payment_number' => $this->payment_number,
            'invoice_id' => $this->invoice_id,
            'invoice' => $this->whenLoaded('invoice', fn () => ['id' => $this->invoice->id, 'invoice_number' => $this->invoice->invoice_number, 'total_amount' => $this->invoice->total_amount, 'due_amount' => $this->invoice->due_amount]),
            'buyer_id' => $this->buyer_id,
            'customer_id' => $this->customer_id,
            'buyer' => $this->whenLoaded('buyer', fn () => ['id' => $this->buyer->id, 'code' => $this->buyer->code, 'name' => $this->buyer->name]),
            'customer' => $this->whenLoaded('customer', fn () => ['id' => $this->customer->id, 'code' => $this->customer->code, 'name' => $this->customer->name]),
            'payment_date' => $this->payment_date?->toDateString(),
            'amount' => $this->amount,
            'payment_method' => $this->payment_method,
            'reference_number' => $this->reference_number,
            'idempotency_key' => $this->idempotency_key,
            'status' => $this->status,
            'remarks' => $this->remarks,
            'received_by' => $this->received_by,
            'receiver' => $this->whenLoaded('receiver', fn () => ['id' => $this->receiver->id, 'name' => $this->receiver->name, 'email' => $this->receiver->email]),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
