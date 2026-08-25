<?php

declare(strict_types=1);

namespace App\Resources\Procurement;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ProcurementStatusHistoryResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'document_type' => $this->document_type,
            'document_id' => $this->document_id,
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
