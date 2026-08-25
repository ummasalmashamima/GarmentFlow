<?php

declare(strict_types=1);

namespace App\Services\Planning;

use App\Models\SupplyPlan;
use App\Models\User;
use App\Services\MasterData\AuditLogService;
use App\Services\Orders\BuyerOrderWorkflow;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class SupplyPlanningService
{
    public function __construct(
        private readonly SupplyPlanningCalculationService $calculationService,
        private readonly PlanningPeriodService $periodService,
        private readonly AuditLogService $auditLogService,
        private readonly BuyerOrderWorkflow $workflow,
    ) {}

    /**
     * @param  array<string, mixed>  $filters
     */
    public function paginate(array $filters): LengthAwarePaginator
    {
        $query = SupplyPlan::query()->with(['product', 'productVariant', 'creator']);

        if ($filters['search'] ?? null) {
            $search = (string) $filters['search'];
            $query->where(function (Builder $builder) use ($search): void {
                $builder->where('status', 'like', "%{$search}%")
                    ->orWhereHas('product', function (Builder $productQuery) use ($search): void {
                        $productQuery->where('code', 'like', "%{$search}%")
                            ->orWhere('name', 'like', "%{$search}%");
                    })
                    ->orWhereHas('productVariant', function (Builder $variantQuery) use ($search): void {
                        $variantQuery->where('sku', 'like', "%{$search}%")
                            ->orWhere('variant_name', 'like', "%{$search}%");
                    });
            });
        }

        foreach ([
            'status' => 'status',
            'period_type' => 'period_type',
            'product_id' => 'product_id',
            'product_variant_id' => 'product_variant_id',
        ] as $filter => $column) {
            if (($filters[$filter] ?? null) !== null && $filters[$filter] !== '') {
                if ($filters[$filter] === 'null' && $column === 'product_variant_id') {
                    $query->whereNull($column);
                } else {
                    $query->where($column, $filters[$filter]);
                }
            }
        }

        foreach ([
            'period_start_from' => ['period_start', '>='],
            'period_start_to' => ['period_start', '<='],
            'period_end_from' => ['period_end', '>='],
            'period_end_to' => ['period_end', '<='],
        ] as $filter => [$column, $operator]) {
            if (($filters[$filter] ?? null) !== null && $filters[$filter] !== '') {
                $query->where($column, $operator, $filters[$filter]);
            }
        }

        $sort = (string) ($filters['sort'] ?? 'period_start');
        $allowedSorts = ['id', 'period_start', 'period_end', 'required_quantity', 'planned_production_quantity', 'status', 'created_at'];
        if (! in_array($sort, $allowedSorts, true)) {
            $sort = 'period_start';
        }

        $direction = (string) ($filters['direction'] ?? 'desc');
        if (! in_array($direction, ['asc', 'desc'], true)) {
            $direction = 'desc';
        }

        return $query->orderBy($sort, $direction)
            ->paginate((int) ($filters['per_page'] ?? 15))
            ->withQueryString();
    }

    public function find(SupplyPlan $supplyPlan): SupplyPlan
    {
        return $supplyPlan->load(['product', 'productVariant', 'creator', 'materialRequirementSources']);
    }

    /**
     * @param  array<string, mixed>  $attributes
     * @return array<string, mixed>
     */
    public function preview(array $attributes): array
    {
        return $this->calculationService->calculate($attributes);
    }

    /**
     * @param  array<string, mixed>  $attributes
     * @return Collection<int, SupplyPlan>
     */
    public function generate(array $attributes, User $actor): Collection
    {
        return DB::transaction(function () use ($attributes, $actor): Collection {
            $period = $this->periodService->normalize($attributes);
            $keys = $this->planningKeys($attributes, $period);
            if ($keys === []) {
                throw ValidationException::withMessages(['planning' => 'No firm order or active forecast demand exists for the selected planning period.']);
            }

            $availability = $this->availabilityMap($attributes['availability'] ?? []);
            $plans = new Collection;

            foreach ($keys as $key) {
                $calculation = $this->calculationService->calculate([
                    ...$period,
                    'product_id' => $key['product_id'],
                    'product_variant_id' => $key['product_variant_id'],
                    'available_quantity' => $attributes['available_quantity'] ?? ($availability[$this->key($key['product_id'], $key['product_variant_id'])] ?? null),
                ]);
                $plan = $this->findExisting($key, $period);
                $oldValues = $plan?->attributesToArray();
                $attributesToSave = [
                    'product_id' => $key['product_id'],
                    'product_variant_id' => $key['product_variant_id'],
                    ...$period,
                    'confirmed_order_quantity' => $calculation['confirmed_order_quantity'],
                    'forecast_quantity' => $calculation['forecast_quantity'],
                    'required_quantity' => $calculation['required_quantity'],
                    'available_quantity' => $calculation['available_quantity'],
                    'planned_production_quantity' => $calculation['planned_production_quantity'],
                    'status' => $calculation['status'],
                    'created_by' => $actor->getKey(),
                    'notes' => $attributes['notes'] ?? $plan?->notes,
                ];

                if ($plan === null) {
                    $plan = SupplyPlan::query()->create($attributesToSave);
                    $action = 'created';
                } else {
                    $plan->forceFill($attributesToSave)->save();
                    $action = 'recalculated';
                }

                $this->auditLogService->record($actor, 'supply-plans', $plan, $action, $oldValues, $plan->attributesToArray());
                $plans->push($this->find($plan->refresh()));
            }

            return $plans;
        });
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function recalculate(SupplyPlan $supplyPlan, array $attributes, User $actor): SupplyPlan
    {
        return DB::transaction(function () use ($supplyPlan, $attributes, $actor): SupplyPlan {
            $period = $this->periodService->normalize([
                'period_type' => $supplyPlan->period_type,
                'period_start' => $supplyPlan->period_start?->toDateString(),
                'period_end' => $supplyPlan->period_end?->toDateString(),
            ]);
            $calculation = $this->calculationService->calculate([
                ...$period,
                'product_id' => $supplyPlan->product_id,
                'product_variant_id' => $supplyPlan->product_variant_id,
                'available_quantity' => $attributes['available_quantity'] ?? null,
            ]);
            $oldValues = $supplyPlan->attributesToArray();
            $supplyPlan->forceFill([
                'confirmed_order_quantity' => $calculation['confirmed_order_quantity'],
                'forecast_quantity' => $calculation['forecast_quantity'],
                'required_quantity' => $calculation['required_quantity'],
                'available_quantity' => $calculation['available_quantity'],
                'planned_production_quantity' => $calculation['planned_production_quantity'],
                'status' => $calculation['status'],
            ])->save();
            $this->auditLogService->record($actor, 'supply-plans', $supplyPlan, 'recalculated', $oldValues, $supplyPlan->attributesToArray());

            return $this->find($supplyPlan->refresh());
        });
    }

    /**
     * @param  array<string, mixed>  $attributes
     * @param  array{period_type: string, period_start: string, period_end: string}  $period
     * @return array<int, array{product_id: int, product_variant_id: ?int}>
     */
    private function planningKeys(array $attributes, array $period): array
    {
        $productId = $attributes['product_id'] ?? null;
        $variantId = $this->nullableId($attributes['product_variant_id'] ?? null);
        if ($productId !== null && $productId !== '') {
            return [['product_id' => (int) $productId, 'product_variant_id' => $variantId]];
        }

        $keys = DB::table('buyer_order_items')
            ->join('buyer_orders', 'buyer_orders.id', '=', 'buyer_order_items.buyer_order_id')
            ->whereIn('buyer_orders.status', $this->workflow->firmDemandStatuses())
            ->whereBetween('buyer_orders.delivery_date', [$period['period_start'], $period['period_end']])
            ->whereNull('buyer_orders.deleted_at')
            ->select(['buyer_order_items.product_id', 'buyer_order_items.product_variant_id'])
            ->distinct()
            ->get()
            ->map(fn ($row): array => ['product_id' => (int) $row->product_id, 'product_variant_id' => $row->product_variant_id === null ? null : (int) $row->product_variant_id])
            ->all();

        $forecastKeys = DB::table('demand_forecasts')
            ->where('period_type', $period['period_type'])
            ->where('period_start', $period['period_start'])
            ->where('status', 'active')
            ->whereNull('deleted_at')
            ->select(['product_id', 'product_variant_id'])
            ->distinct()
            ->get()
            ->map(fn ($row): array => ['product_id' => (int) $row->product_id, 'product_variant_id' => $row->product_variant_id === null ? null : (int) $row->product_variant_id])
            ->all();

        $combined = [];
        foreach (array_merge($keys, $forecastKeys) as $key) {
            $combined[$this->key($key['product_id'], $key['product_variant_id'])] = $key;
        }

        return array_values($combined);
    }

    /**
     * @param  array<int, array<string, mixed>>  $rows
     * @return array<string, float>
     */
    private function availabilityMap(array $rows): array
    {
        $map = [];
        foreach ($rows as $row) {
            $map[$this->key((int) $row['product_id'], $this->nullableId($row['product_variant_id'] ?? null))] = (float) $row['available_quantity'];
        }

        return $map;
    }

    /**
     * @param  array{product_id: int, product_variant_id: ?int}  $key
     * @param  array{period_type: string, period_start: string, period_end: string}  $period
     */
    private function findExisting(array $key, array $period): ?SupplyPlan
    {
        $query = SupplyPlan::query()
            ->where('product_id', $key['product_id'])
            ->where('period_type', $period['period_type'])
            ->where('period_start', $period['period_start']);
        $key['product_variant_id'] === null ? $query->whereNull('product_variant_id') : $query->where('product_variant_id', $key['product_variant_id']);

        return $query->first();
    }

    private function key(int $productId, ?int $variantId): string
    {
        return $productId.':'.($variantId ?? 'product');
    }

    private function nullableId(mixed $value): ?int
    {
        return $value === null || $value === '' ? null : (int) $value;
    }
}
