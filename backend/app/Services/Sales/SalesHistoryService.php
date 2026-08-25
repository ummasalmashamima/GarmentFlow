<?php

declare(strict_types=1);

namespace App\Services\Sales;

use App\Models\AuditLog;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;

final class SalesHistoryService
{
    /** @param array<string, mixed> $filters */
    public function paginate(array $filters): LengthAwarePaginator
    {
        $query = AuditLog::query()->with('user')->where('module', 'sales-orders');
        if (($filters['search'] ?? null) !== null && $filters['search'] !== '') {
            $search = (string) $filters['search'];
            $query->where(function (Builder $builder) use ($search): void {
                $builder->where('record_id', 'like', "%{$search}%")
                    ->orWhere('action', 'like', "%{$search}%")
                    ->orWhere('record_type', 'like', "%{$search}%")
                    ->orWhereHas('user', fn (Builder $user) => $user->where('name', 'like', "%{$search}%")->orWhere('email', 'like', "%{$search}%"));
            });
        }
        if (($filters['action'] ?? null) !== null && $filters['action'] !== '') {
            $query->where('action', $filters['action']);
        }
        foreach ([
            'date_from' => ['created_at', '>='],
            'date_to' => ['created_at', '<='],
        ] as $filter => [$column, $operator]) {
            if (($filters[$filter] ?? null) !== null && $filters[$filter] !== '') {
                $query->whereDate($column, $operator, $filters[$filter]);
            }
        }
        $sort = in_array(($filters['sort'] ?? 'id'), ['id', 'action', 'record_id', 'created_at'], true)
            ? (string) ($filters['sort'] ?? 'id') : 'id';
        $direction = ($filters['direction'] ?? 'desc') === 'asc' ? 'asc' : 'desc';

        return $query->orderBy($sort, $direction)->paginate((int) ($filters['per_page'] ?? 15))->withQueryString();
    }
}
