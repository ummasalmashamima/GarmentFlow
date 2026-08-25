<?php

declare(strict_types=1);

namespace App\Services\Production;

use App\Models\ProductionOrder;
use App\Models\ProductionProgress;
use App\Models\User;
use App\Services\MasterData\AuditLogService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class ProductionProgressService
{
    public function __construct(private readonly AuditLogService $auditLogService) {}

    public function paginate(array $filters): LengthAwarePaginator
    {
        $query = ProductionProgress::query()->with(['productionOrder.product', 'productionOrder.productVariant', 'recorder']);
        if (($filters['search'] ?? null) !== null && $filters['search'] !== '') {
            $search = (string) $filters['search'];
            $query->where(function (Builder $builder) use ($search): void {
                $builder->where('production_date', 'like', "%{$search}%")
                    ->orWhere('remarks', 'like', "%{$search}%")
                    ->orWhereHas('productionOrder', fn (Builder $q) => $q->where('order_number', 'like', "%{$search}%"));
            });
        }
        foreach (['production_order_id', 'recorded_by'] as $field) {
            if (($filters[$field] ?? null) !== null && $filters[$field] !== '') {
                $query->where($field, $filters[$field]);
            }
        }
        foreach ([
            'date_from' => ['production_date', '>='],
            'date_to' => ['production_date', '<='],
        ] as $filter => [$column, $operator]) {
            if (($filters[$filter] ?? null) !== null && $filters[$filter] !== '') {
                $query->where($column, $operator, $filters[$filter]);
            }
        }
        $sort = in_array(($filters['sort'] ?? 'id'), ['id', 'production_date', 'completed_quantity', 'progress_percentage', 'created_at'], true)
            ? (string) ($filters['sort'] ?? 'id') : 'id';
        $direction = ($filters['direction'] ?? 'desc') === 'asc' ? 'asc' : 'desc';

        return $query->orderBy($sort, $direction)->paginate((int) ($filters['per_page'] ?? 15))->withQueryString();
    }

    public function record(ProductionOrder $order, array $attributes, User $actor): ProductionProgress
    {
        return DB::transaction(function () use ($order, $attributes, $actor): ProductionProgress {
            $locked = ProductionOrder::query()->lockForUpdate()->findOrFail($order->getKey());
            if ($locked->status !== ProductionWorkflow::IN_PROGRESS) {
                throw ValidationException::withMessages(['status' => 'Progress can only be recorded for Production Orders in progress.']);
            }
            $planned = (float) $locked->planned_quantity;
            $completed = (float) ($attributes['completed_quantity'] ?? $locked->completed_quantity);
            $rejected = (float) ($attributes['rejected_quantity'] ?? $locked->rejected_quantity);
            if ($completed < (float) $locked->completed_quantity) {
                throw ValidationException::withMessages(['completed_quantity' => 'Completed quantity cannot decrease.']);
            }
            if ($rejected < (float) $locked->rejected_quantity) {
                throw ValidationException::withMessages(['rejected_quantity' => 'Rejected quantity cannot decrease.']);
            }
            if (($completed > $planned || $rejected > $planned) && ! $actor->hasPermission('production.override')) {
                throw ValidationException::withMessages(['completed_quantity' => 'Progress cannot exceed the planned quantity without production.override.']);
            }
            $remaining = max($planned - $completed, 0);
            $percentage = $planned > 0 ? ($completed / $planned) * 100 : 0;
            $progress = $locked->progress()->create([
                'planned_quantity' => $planned,
                'completed_quantity' => $completed,
                'rejected_quantity' => $rejected,
                'remaining_quantity' => $remaining,
                'progress_percentage' => $percentage,
                'production_date' => $attributes['production_date'] ?? now()->toDateString(),
                'recorded_by' => $actor->getKey(),
                'remarks' => $attributes['remarks'] ?? null,
            ]);
            $old = $locked->attributesToArray();
            $locked->update(['completed_quantity' => $completed, 'rejected_quantity' => $rejected]);
            $this->auditLogService->record($actor, 'production-progress', $progress, 'recorded', null, $progress->attributesToArray());
            $this->auditLogService->record($actor, 'production-orders', $locked, 'progress_updated', $old, $locked->fresh()->attributesToArray());

            return $progress->load(['productionOrder.product', 'productionOrder.productVariant', 'recorder']);
        });
    }
}
