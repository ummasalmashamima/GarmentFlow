<?php

declare(strict_types=1);

namespace App\Services\Production;

use App\Models\AuditLog;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;

final class ProductionHistoryService
{
    /** @var array<int, string> */
    private array $modules = ['production-plans', 'production-orders', 'production-progress', 'material-consumptions', 'finished-goods'];

    public function paginate(array $filters): LengthAwarePaginator
    {
        $query = AuditLog::query()->with('user')->whereIn('module', $this->modules);
        if (($filters['search'] ?? null) !== null && $filters['search'] !== '') {
            $search = (string) $filters['search'];
            $query->where(function (Builder $builder) use ($search): void {
                $builder->where('module', 'like', "%{$search}%")
                    ->orWhere('action', 'like', "%{$search}%")
                    ->orWhere('record_type', 'like', "%{$search}%")
                    ->orWhere('record_id', $search);
            });
        }
        foreach (['module', 'action', 'record_id', 'user_id'] as $field) {
            if (($filters[$field] ?? null) !== null && $filters[$field] !== '') {
                $query->where($field, $filters[$field]);
            }
        }
        if (($filters['date_from'] ?? null) !== null && $filters['date_from'] !== '') {
            $query->whereDate('created_at', '>=', $filters['date_from']);
        }
        if (($filters['date_to'] ?? null) !== null && $filters['date_to'] !== '') {
            $query->whereDate('created_at', '<=', $filters['date_to']);
        }
        $sort = in_array(($filters['sort'] ?? 'created_at'), ['id', 'module', 'action', 'record_id', 'created_at'], true)
            ? (string) ($filters['sort'] ?? 'created_at') : 'created_at';
        $direction = ($filters['direction'] ?? 'desc') === 'asc' ? 'asc' : 'desc';

        return $query->orderBy($sort, $direction)->paginate((int) ($filters['per_page'] ?? 15))->withQueryString();
    }
}
