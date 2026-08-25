<?php

declare(strict_types=1);

namespace App\Resources\Orders;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class OrderPlanningInputResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'status' => $this->status,
            'total_quantity' => $this->total_quantity,
            'notes' => $this->notes,
            'prepared_at' => $this->prepared_at?->toISOString(),
            'prepared_by' => $this->whenLoaded('preparer', fn (): array => [
                'id' => $this->preparer->id,
                'name' => $this->preparer->name,
                'email' => $this->preparer->email,
            ]),
        ];
    }
}
