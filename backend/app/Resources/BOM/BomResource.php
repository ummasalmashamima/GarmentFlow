<?php

declare(strict_types=1);

namespace App\Resources\BOM;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class BomResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $data = [
            'id' => $this->resource->getKey(),
            'product_id' => $this->resource->product_id,
            'code' => $this->resource->code,
            'name' => $this->resource->name,
            'status' => $this->resource->status,
            'description' => $this->resource->description,
            'created_at' => $this->resource->created_at?->toISOString(),
            'updated_at' => $this->resource->updated_at?->toISOString(),
        ];

        if ($this->resource->relationLoaded('product')) {
            $data['product'] = $this->relationSummary($this->resource->product);
        }

        if ($this->resource->relationLoaded('activeVersion')) {
            $data['active_version'] = new BomVersionResource($this->resource->activeVersion);
        }

        if ($this->resource->relationLoaded('versions')) {
            $data['versions'] = BomVersionResource::collection($this->resource->versions);
        }

        return $data;
    }

    /**
     * @return array<string, mixed>
     */
    private function relationSummary(Model $model): array
    {
        $summary = ['id' => $model->getKey()];

        foreach (['code', 'sku', 'name', 'status'] as $field) {
            if ($model->getAttribute($field) !== null) {
                $summary[$field] = $model->getAttribute($field);
            }
        }

        return $summary;
    }
}
