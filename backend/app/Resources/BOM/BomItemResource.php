<?php

declare(strict_types=1);

namespace App\Resources\BOM;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class BomItemResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $data = [
            'id' => $this->resource->getKey(),
            'bom_version_id' => $this->resource->bom_version_id,
            'material_id' => $this->resource->material_id,
            'unit_id' => $this->resource->unit_id,
            'quantity' => (float) $this->resource->quantity,
            'wastage_percentage' => (float) $this->resource->wastage_percentage,
            'line_number' => $this->resource->line_number,
            'notes' => $this->resource->notes,
            'created_at' => $this->resource->created_at?->toISOString(),
            'updated_at' => $this->resource->updated_at?->toISOString(),
        ];

        if ($this->resource->relationLoaded('material')) {
            $data['material'] = $this->relationSummary($this->resource->material);
        }

        if ($this->resource->relationLoaded('unit')) {
            $data['unit'] = $this->relationSummary($this->resource->unit);
        }

        return $data;
    }

    /**
     * @return array<string, mixed>
     */
    private function relationSummary(Model $model): array
    {
        $summary = ['id' => $model->getKey()];

        foreach (['code', 'sku', 'name', 'symbol', 'status'] as $field) {
            if ($model->getAttribute($field) !== null) {
                $summary[$field] = $model->getAttribute($field);
            }
        }

        return $summary;
    }
}
