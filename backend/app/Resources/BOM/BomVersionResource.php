<?php

declare(strict_types=1);

namespace App\Resources\BOM;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class BomVersionResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $data = [
            'id' => $this->resource->getKey(),
            'bom_header_id' => $this->resource->bom_header_id,
            'version_number' => $this->resource->version_number,
            'effective_from' => $this->resource->effective_from?->toDateString(),
            'effective_to' => $this->resource->effective_to?->toDateString(),
            'status' => $this->resource->status,
            'notes' => $this->resource->notes,
            'items_count' => isset($this->resource->items_count) ? (int) $this->resource->items_count : null,
            'created_at' => $this->resource->created_at?->toISOString(),
            'updated_at' => $this->resource->updated_at?->toISOString(),
        ];

        if ($this->resource->relationLoaded('bomHeader')) {
            $data['bom'] = $this->relationSummary($this->resource->bomHeader);
        }

        if ($this->resource->relationLoaded('items')) {
            $data['items'] = BomItemResource::collection($this->resource->items);
        }

        return $data;
    }

    /**
     * @return array<string, mixed>
     */
    private function relationSummary(Model $model): array
    {
        $summary = ['id' => $model->getKey()];

        foreach (['code', 'name', 'status'] as $field) {
            if ($model->getAttribute($field) !== null) {
                $summary[$field] = $model->getAttribute($field);
            }
        }

        if ($model->relationLoaded('product')) {
            $summary['product'] = $this->relationSummary($model->product);
        }

        return $summary;
    }
}
