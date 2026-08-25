<?php

declare(strict_types=1);

namespace App\Resources\MasterData;

use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class MasterDataResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $data = collect($this->resource->attributesToArray())
            ->except(['deleted_at'])
            ->all();

        foreach ($this->resource->getRelations() as $name => $relation) {
            $data[$name] = $relation instanceof EloquentCollection
                ? $relation->map(fn (Model $model): array => $this->relationSummary($model))->values()->all()
                : ($relation instanceof Model ? $this->relationSummary($relation) : $relation);
        }

        return $data;
    }

    /**
     * @return array<string, mixed>
     */
    private function relationSummary(Model $model): array
    {
        $summary = ['id' => $model->getKey()];

        foreach (['code', 'sku', 'name', 'title', 'status'] as $field) {
            if ($model->getAttribute($field) !== null) {
                $summary[$field] = $model->getAttribute($field);
            }
        }

        return $summary;
    }
}
