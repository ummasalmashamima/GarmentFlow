<?php

declare(strict_types=1);

namespace App\Services\MasterData;

use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

final class AuditLogService
{
    /**
     * @param  array<string, mixed>|null  $oldValues
     * @param  array<string, mixed>|null  $newValues
     */
    public function record(User $actor, string $module, Model $model, string $action, ?array $oldValues, ?array $newValues): void
    {
        AuditLog::query()->create([
            'user_id' => $actor->getKey(),
            'module' => $module,
            'record_type' => $model::class,
            'record_id' => $model->getKey(),
            'action' => $action,
            'old_values' => $oldValues,
            'new_values' => $newValues,
        ]);
    }

    /** @return Collection<int, AuditLog> */
    public function forRecord(string $module, int $recordId): Collection
    {
        return AuditLog::query()
            ->with('user')
            ->where('module', $module)
            ->where('record_id', $recordId)
            ->latest('id')
            ->get();
    }
}
