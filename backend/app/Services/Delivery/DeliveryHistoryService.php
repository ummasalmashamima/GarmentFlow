<?php

declare(strict_types=1);

namespace App\Services\Delivery;

use App\Models\AuditLog;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;

final class DeliveryHistoryService
{
    /** @param array<string, mixed> $filters */
    public function list(array $filters = []): LengthAwarePaginator
    {
        $query = AuditLog::query()->with('user')->where('module', 'deliveries');
        if (($filters['search'] ?? null) !== null && $filters['search'] !== '') {
            $search = (string) $filters['search'];
            $query->where(function (Builder $builder) use ($search): void {
                $builder->where('action', 'like', "%{$search}%")
                    ->orWhere('record_type', 'like', "%{$search}%")
                    ->orWhere('record_id', 'like', "%{$search}%")
                    ->orWhereHas('user', fn (Builder $user) => $user->where('name', 'like', "%{$search}%")->orWhere('email', 'like', "%{$search}%"));
            });
        }
        if (($filters['action'] ?? null) !== null && $filters['action'] !== '') {
            $query->where('action', $filters['action']);
        }
        foreach ([['date_from', '>='], ['date_to', '<=']] as [$field, $operator]) {
            if (($filters[$field] ?? null) !== null && $filters[$field] !== '') {
                $query->whereDate('created_at', $operator, $filters[$field]);
            }
        }
        $requestedSort = (string) ($filters['sort'] ?? 'id');
        $sort = in_array($requestedSort, ['id', 'created_at', 'action', 'module'], true) ? $requestedSort : 'id';
        $direction = ($filters['direction'] ?? 'desc') === 'asc' ? 'asc' : 'desc';

        return $query->orderBy($sort, $direction)->paginate((int) ($filters['per_page'] ?? 10))->withQueryString();
    }
}
