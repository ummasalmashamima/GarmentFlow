<?php

declare(strict_types=1);

namespace App\Resources\Orders;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class OrderStatusHistoryResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'previous_status' => $this->previous_status,
            'new_status' => $this->new_status,
            'remarks' => $this->remarks,
            'changed_by' => $this->whenLoaded('changer', fn (): array => [
                'id' => $this->changer->id,
                'name' => $this->changer->name,
                'email' => $this->changer->email,
            ]),
            'created_at' => $this->created_at?->toISOString(),
        ];
    }
}
