<?php

declare(strict_types=1);

namespace App\Services\MasterData;

use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

final class MasterDataService
{
    public function __construct(private readonly AuditLogService $auditLogService) {}

    /**
     * @param  array<string, mixed>  $filters
     */
    public function paginate(string $resource, array $filters): LengthAwarePaginator
    {
        $definition = MasterDataRegistry::get($resource);
        $query = $this->baseQuery($definition);

        if ($filters['search'] ?? null) {
            $search = $filters['search'];
            $query->where(function (Builder $builder) use ($definition, $search): void {
                foreach ($definition['searchable'] as $index => $field) {
                    $method = $index === 0 ? 'where' : 'orWhere';
                    $builder->{$method}($field, 'like', "%{$search}%");
                }
            });
        }

        foreach ($definition['filterable'] as $field) {
            if (($filters[$field] ?? null) !== null && $filters[$field] !== '') {
                $query->where($field, $filters[$field]);
            }
        }

        $sort = $filters['sort'] ?? 'id';
        $direction = $filters['direction'] ?? 'desc';

        return $query
            ->orderBy($sort, $direction)
            ->paginate((int) ($filters['per_page'] ?? 15))
            ->withQueryString();
    }

    public function find(string $resource, int|string $id): Model
    {
        $definition = MasterDataRegistry::get($resource);

        return $this->baseQuery($definition)->findOrFail($id);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function create(string $resource, array $attributes, User $actor): Model
    {
        $definition = MasterDataRegistry::get($resource);

        return DB::transaction(function () use ($resource, $definition, $attributes, $actor): Model {
            $record = app($definition['model']);
            $record->fill($attributes);
            $record->save();
            $record->load($definition['relations']);

            $this->auditLogService->record($actor, $resource, $record, 'created', null, $record->attributesToArray());

            return $record;
        });
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function update(string $resource, int|string $id, array $attributes, User $actor): Model
    {
        $definition = MasterDataRegistry::get($resource);

        return DB::transaction(function () use ($resource, $definition, $id, $attributes, $actor): Model {
            $record = $this->find($resource, $id);
            $oldValues = $record->attributesToArray();
            $record->fill($attributes);
            $record->save();
            $record->load($definition['relations']);

            $this->auditLogService->record($actor, $resource, $record, 'updated', $oldValues, $record->attributesToArray());

            return $record;
        });
    }

    /**
     * @return array{record: Model, deactivated: bool}
     */
    public function deleteOrDeactivate(string $resource, int|string $id, User $actor): array
    {
        $definition = MasterDataRegistry::get($resource);

        return DB::transaction(function () use ($resource, $definition, $id, $actor): array {
            $record = $this->find($resource, $id);
            $oldValues = $record->attributesToArray();
            $hasDependencies = false;

            foreach ($definition['dependency_relations'] as $relation) {
                if ($record->{$relation}()->exists()) {
                    $hasDependencies = true;
                    break;
                }
            }

            if ($hasDependencies) {
                $record->forceFill(['status' => 'inactive']);
                $record->save();
                $action = 'deactivated';
            } else {
                $record->delete();
                $action = 'deleted';
            }

            $newValues = $record->attributesToArray();
            $this->auditLogService->record($actor, $resource, $record, $action, $oldValues, $newValues);

            return [
                'record' => $record->load($definition['relations']),
                'deactivated' => $hasDependencies,
            ];
        });
    }

    /**
     * @return array<int, array{id: int, code: string, name: string}>
     */
    public function options(string $resource): array
    {
        $definition = MasterDataRegistry::get($resource);
        $model = app($definition['model']);
        $codeColumn = array_key_exists('code', $definition['fields']) ? 'code' : 'sku';
        $nameColumn = array_key_exists('name', $definition['fields']) ? 'name' : 'variant_name';

        $relationKeys = collect($definition['fields'])
            ->filter(fn (array $field): bool => ($field['type'] ?? null) === 'relation')
            ->keys()
            ->all();
        $columns = array_values(array_unique(['id', $codeColumn, $nameColumn, ...$relationKeys]));

        return $model::query()
            ->where('status', 'active')
            ->orderBy($nameColumn)
            ->get($columns)
            ->map(function (Model $record) use ($relationKeys, $codeColumn, $nameColumn): array {
                $option = [
                    'id' => $record->getKey(),
                    'code' => $record->getAttribute($codeColumn),
                    'name' => $record->getAttribute($nameColumn),
                ];

                foreach ($relationKeys as $relationKey) {
                    $option[$relationKey] = $record->getAttribute($relationKey);
                }

                return $option;
            })
            ->values()
            ->all();
    }

    /**
     * @param  array<string, mixed>  $definition
     * @return Builder<Model>
     */
    private function baseQuery(array $definition): Builder
    {
        $model = app($definition['model']);

        return $model::query()->with($definition['relations']);
    }
}
