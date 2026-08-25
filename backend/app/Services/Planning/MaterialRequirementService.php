<?php

declare(strict_types=1);

namespace App\Services\Planning;

use App\Models\MrpRun;
use App\Models\User;
use App\Services\MasterData\AuditLogService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

final class MaterialRequirementService
{
    public function __construct(
        private readonly MaterialRequirementCalculationService $calculationService,
        private readonly AuditLogService $auditLogService,
    ) {}

    /**
     * @param  array<string, mixed>  $filters
     */
    public function paginate(array $filters): LengthAwarePaginator
    {
        $query = MrpRun::query()->with('creator')->withCount('materialRequirements');

        if ($filters['search'] ?? null) {
            $search = (string) $filters['search'];
            $query->where(function (Builder $builder) use ($search): void {
                $builder->where('run_number', 'like', "%{$search}%")
                    ->orWhere('status', 'like', "%{$search}%");
            });
        }
        if (($filters['status'] ?? null) !== null && $filters['status'] !== '') {
            $query->where('status', $filters['status']);
        }
        foreach ([
            'planning_date_from' => ['planning_date', '>='],
            'planning_date_to' => ['planning_date', '<='],
        ] as $filter => [$column, $operator]) {
            if (($filters[$filter] ?? null) !== null && $filters[$filter] !== '') {
                $query->where($column, $operator, $filters[$filter]);
            }
        }

        $sort = (string) ($filters['sort'] ?? 'planning_date');
        $allowedSorts = ['id', 'run_number', 'planning_date', 'total_gross_quantity', 'status', 'created_at'];
        if (! in_array($sort, $allowedSorts, true)) {
            $sort = 'planning_date';
        }
        $direction = (string) ($filters['direction'] ?? 'desc');
        if (! in_array($direction, ['asc', 'desc'], true)) {
            $direction = 'desc';
        }

        return $query->orderBy($sort, $direction)
            ->paginate((int) ($filters['per_page'] ?? 15))
            ->withQueryString();
    }

    public function find(MrpRun $run): MrpRun
    {
        return $run->load([
            'creator',
            'materialRequirements.material',
            'materialRequirements.unit',
            'materialRequirements.sources.supplyPlan',
            'materialRequirements.sources.product',
            'materialRequirements.sources.productVariant',
            'materialRequirements.sources.material',
            'materialRequirements.sources.unit',
            'materialRequirements.sources.bomVersion',
            'materialRequirements.sources.bomItem',
        ]);
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
     */
    public function generate(array $attributes, User $actor): MrpRun
    {
        return DB::transaction(function () use ($attributes, $actor): MrpRun {
            $calculation = $this->calculationService->calculate($attributes);
            $run = MrpRun::query()->create([
                'run_number' => $this->generateRunNumber(),
                'status' => 'calculated',
                'planning_date' => $attributes['planning_date'] ?? now()->toDateString(),
                'total_gross_quantity' => $calculation['total_gross_quantity'],
                'total_net_quantity' => $calculation['total_net_quantity'],
                'inventory_data_available' => $calculation['inventory_data_available'],
                'created_by' => $actor->getKey(),
                'calculated_at' => now(),
                'notes' => $attributes['notes'] ?? null,
            ]);

            foreach ($calculation['lines'] as $line) {
                $requirement = $run->materialRequirements()->create([
                    'material_id' => $line['material']['id'],
                    'unit_id' => $line['unit']['id'],
                    'gross_quantity' => $line['gross_quantity'],
                    'available_quantity' => $line['available_quantity'],
                    'allocated_quantity' => $line['allocated_quantity'],
                    'net_quantity' => $line['net_quantity'],
                    'status' => $line['status'],
                ]);
                $this->auditLogService->record($actor, 'material-requirements', $requirement, 'created', null, $requirement->attributesToArray());

                foreach ($line['sources'] as $source) {
                    $requirement->sources()->create($source);
                }
            }

            $this->auditLogService->record($actor, 'mrp-runs', $run, 'created', null, $run->attributesToArray());

            return $this->find($run->refresh());
        });
    }

    private function generateRunNumber(): string
    {
        $prefix = 'MRP-'.now()->format('Ymd').'-';
        $last = MrpRun::query()
            ->where('run_number', 'like', $prefix.'%')
            ->orderByDesc('id')
            ->value('run_number');
        $next = $last === null ? 1 : ((int) substr((string) $last, -4)) + 1;

        return $prefix.str_pad((string) $next, 4, '0', STR_PAD_LEFT);
    }
}
