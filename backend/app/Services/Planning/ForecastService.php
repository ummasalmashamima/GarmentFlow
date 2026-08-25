<?php

declare(strict_types=1);

namespace App\Services\Planning;

use App\Models\DemandForecast;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\User;
use App\Services\MasterData\AuditLogService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class ForecastService
{
    public function __construct(
        private readonly ForecastCalculationService $calculationService,
        private readonly PlanningPeriodService $periodService,
        private readonly AuditLogService $auditLogService,
    ) {}

    /**
     * @param  array<string, mixed>  $filters
     */
    public function paginate(array $filters): LengthAwarePaginator
    {
        $query = DemandForecast::query()->with(['product', 'productVariant', 'creator']);

        if ($filters['search'] ?? null) {
            $search = (string) $filters['search'];
            $query->where(function (Builder $builder) use ($search): void {
                $builder->where('method', 'like', "%{$search}%")
                    ->orWhere('status', 'like', "%{$search}%")
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
        $allowedSorts = ['id', 'period_start', 'period_end', 'forecast_quantity', 'status', 'created_at'];
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

    public function find(DemandForecast $forecast): DemandForecast
    {
        return $forecast->load(['product', 'productVariant', 'creator']);
    }

    /**
     * @param  array<string, mixed>  $attributes
     * @return array<string, mixed>
     */
    public function preview(array $attributes): array
    {
        $this->validateReferences($attributes);

        return $this->calculationService->calculate($attributes);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function create(array $attributes, User $actor): DemandForecast
    {
        return DB::transaction(function () use ($attributes, $actor): DemandForecast {
            $this->validateReferences($attributes);
            $period = $this->periodService->normalize($attributes);
            $this->assertUnique($attributes, $period);
            $calculation = $this->calculationService->calculate($attributes);

            $forecast = DemandForecast::query()->create([
                'product_id' => (int) $attributes['product_id'],
                'product_variant_id' => $this->nullableId($attributes['product_variant_id'] ?? null),
                ...$period,
                'forecast_quantity' => $calculation['forecast_quantity'],
                'method' => $attributes['method'],
                'status' => 'draft',
                'forecast_date' => $attributes['forecast_date'] ?? now()->toDateString(),
                'confidence_score' => $attributes['confidence_score'] ?? null,
                'accuracy_score' => $attributes['accuracy_score'] ?? null,
                'lookback_periods' => (int) ($attributes['lookback_periods'] ?? 3),
                'calculation_snapshot' => $calculation,
                'created_by' => $actor->getKey(),
                'notes' => $attributes['notes'] ?? null,
            ]);
            $this->auditLogService->record($actor, 'demand-forecasts', $forecast, 'created', null, $forecast->attributesToArray());

            return $this->find($forecast->refresh());
        });
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function update(DemandForecast $forecast, array $attributes, User $actor): DemandForecast
    {
        return DB::transaction(function () use ($forecast, $attributes, $actor): DemandForecast {
            if ($forecast->status !== 'draft') {
                throw ValidationException::withMessages(['status' => 'Only draft forecasts can be edited.']);
            }
            $this->validateReferences($attributes);
            $period = $this->periodService->normalize($attributes);
            $this->assertUnique($attributes, $period, $forecast->getKey());
            $calculation = $this->calculationService->calculate($attributes);
            $oldValues = $forecast->attributesToArray();

            $forecast->forceFill([
                'product_id' => (int) $attributes['product_id'],
                'product_variant_id' => $this->nullableId($attributes['product_variant_id'] ?? null),
                ...$period,
                'forecast_quantity' => $calculation['forecast_quantity'],
                'method' => $attributes['method'],
                'forecast_date' => $attributes['forecast_date'] ?? $forecast->forecast_date?->toDateString() ?? now()->toDateString(),
                'confidence_score' => $attributes['confidence_score'] ?? null,
                'accuracy_score' => $attributes['accuracy_score'] ?? null,
                'lookback_periods' => (int) ($attributes['lookback_periods'] ?? 3),
                'calculation_snapshot' => $calculation,
                'notes' => $attributes['notes'] ?? null,
            ])->save();
            $this->auditLogService->record($actor, 'demand-forecasts', $forecast, 'updated', $oldValues, $forecast->attributesToArray());

            return $this->find($forecast->refresh());
        });
    }

    public function activate(DemandForecast $forecast, User $actor): DemandForecast
    {
        return DB::transaction(function () use ($forecast, $actor): DemandForecast {
            if ($forecast->status !== 'draft') {
                throw ValidationException::withMessages(['status' => 'Only draft forecasts can be activated.']);
            }
            $oldValues = $forecast->attributesToArray();
            $forecast->forceFill(['status' => 'active'])->save();
            $this->auditLogService->record($actor, 'demand-forecasts', $forecast, 'activated', $oldValues, $forecast->attributesToArray());

            return $this->find($forecast->refresh());
        });
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function validateReferences(array $attributes): void
    {
        $productId = (int) ($attributes['product_id'] ?? 0);
        if (! Product::query()->whereKey($productId)->where('status', 'active')->exists()) {
            throw ValidationException::withMessages(['product_id' => 'The selected product must exist and be active.']);
        }

        if (($attributes['product_variant_id'] ?? null) !== null && $attributes['product_variant_id'] !== '') {
            if (! ProductVariant::query()
                ->whereKey((int) $attributes['product_variant_id'])
                ->where('product_id', $productId)
                ->where('status', 'active')
                ->exists()) {
                throw ValidationException::withMessages(['product_variant_id' => 'The selected Product Variant must belong to the selected active product.']);
            }
        }
    }

    /**
     * @param  array<string, mixed>  $attributes
     * @param  array{period_type: string, period_start: string, period_end: string}  $period
     */
    private function assertUnique(array $attributes, array $period, ?int $ignoreId = null): void
    {
        $query = DemandForecast::query()
            ->where('product_id', (int) $attributes['product_id'])
            ->where('period_type', $period['period_type'])
            ->where('period_start', $period['period_start']);

        $variantId = $this->nullableId($attributes['product_variant_id'] ?? null);
        $variantId === null ? $query->whereNull('product_variant_id') : $query->where('product_variant_id', $variantId);

        if ($ignoreId !== null) {
            $query->whereKeyNot($ignoreId);
        }

        if ($query->exists()) {
            throw ValidationException::withMessages(['period_start' => 'A forecast already exists for this Product, Variant, and period.']);
        }
    }

    private function nullableId(mixed $value): ?int
    {
        return $value === null || $value === '' ? null : (int) $value;
    }
}
