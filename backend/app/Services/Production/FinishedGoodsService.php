<?php

declare(strict_types=1);

namespace App\Services\Production;

use App\Models\FinishedGoods;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;

final class FinishedGoodsService
{
    public function paginate(array $filters): LengthAwarePaginator
    {
        $query = FinishedGoods::query()->with(['productionOrder', 'product', 'productVariant', 'unit', 'warehouse', 'warehouseLocation', 'inventoryTransaction', 'recorder']);
        if (($filters['search'] ?? null) !== null && $filters['search'] !== '') {
            $search = (string) $filters['search'];
            $query->where(function (Builder $builder) use ($search): void {
                $builder->where('finished_goods_number', 'like', "%{$search}%")
                    ->orWhereHas('productionOrder', fn (Builder $q) => $q->where('order_number', 'like', "%{$search}%"))
                    ->orWhereHas('product', fn (Builder $q) => $q->where('code', 'like', "%{$search}%")->orWhere('name', 'like', "%{$search}%"))
                    ->orWhereHas('productVariant', fn (Builder $q) => $q->where('sku', 'like', "%{$search}%")->orWhere('variant_name', 'like', "%{$search}%"));
            });
        }
        foreach (['production_order_id', 'product_id', 'product_variant_id', 'warehouse_id', 'warehouse_location_id', 'recorded_by'] as $field) {
            if (($filters[$field] ?? null) !== null && $filters[$field] !== '') {
                $query->where($field, $filters[$field]);
            }
        }
        foreach ([
            'date_from' => ['finished_date', '>='],
            'date_to' => ['finished_date', '<='],
        ] as $filter => [$column, $operator]) {
            if (($filters[$filter] ?? null) !== null && $filters[$filter] !== '') {
                $query->where($column, $operator, $filters[$filter]);
            }
        }
        $sort = in_array(($filters['sort'] ?? 'id'), ['id', 'finished_goods_number', 'quantity', 'finished_date', 'created_at'], true)
            ? (string) ($filters['sort'] ?? 'id') : 'id';
        $direction = ($filters['direction'] ?? 'desc') === 'asc' ? 'asc' : 'desc';

        return $query->orderBy($sort, $direction)->paginate((int) ($filters['per_page'] ?? 15))->withQueryString();
    }

    public function find(FinishedGoods $finishedGoods): FinishedGoods
    {
        return $finishedGoods->load(['productionOrder.product', 'productionOrder.productVariant', 'product', 'productVariant', 'unit', 'warehouse', 'warehouseLocation', 'inventoryTransaction', 'recorder']);
    }
}
