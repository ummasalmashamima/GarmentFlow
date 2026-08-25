<?php

declare(strict_types=1);

namespace App\Resources\Delivery;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class DeliveryTrackingHistoryResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'delivery_id' => $this->delivery_id,
            'previous_status' => $this->previous_status,
            'new_status' => $this->new_status,
            'carrier_name' => $this->carrier_name,
            'tracking_number' => $this->tracking_number,
            'location' => $this->location,
            'changed_by' => $this->changed_by,
            'changer' => $this->whenLoaded('changer', fn () => [
                'id' => $this->changer->id,
                'name' => $this->changer->name,
                'email' => $this->changer->email,
            ]),
            'remarks' => $this->remarks,
            'created_at' => $this->created_at?->toISOString(),
        ];
    }
}
