<?php

declare(strict_types=1);

namespace App\Services\Procurement;

use App\Models\ProcurementStatusHistory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;

final class ProcurementHistoryService
{
    public function paginate(array $filters): LengthAwarePaginator
    {
        $query = ProcurementStatusHistory::query()->with('changer');
        if (($filters['search'] ?? null) !== null && $filters['search'] !== '') {
            $search = (string) $filters['search'];
            $query->where(function (Builder $builder) use ($search): void {
                $builder->where('document_id', 'like', "%{$search}%")
                    ->orWhere('remarks', 'like', "%{$search}%")
                    ->orWhere('previous_status', 'like', "%{$search}%")
                    ->orWhere('new_status', 'like', "%{$search}%");
            });
        }
        foreach (['document_type', 'new_status', 'changed_by'] as $field) {
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
        $direction = ($filters['direction'] ?? 'desc') === 'asc' ? 'asc' : 'desc';

        return $query->orderBy('created_at', $direction)->paginate((int) ($filters['per_page'] ?? 20))->withQueryString();
    }
}
