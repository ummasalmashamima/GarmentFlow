<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\User;

final class WorkspacePolicy
{
    public function viewDashboard(User $user): bool
    {
        return $user->hasPermission('dashboard.view');
    }

    public function viewReport(User $user): bool
    {
        return $user->hasPermission('reports.view');
    }

    public function exportReport(User $user): bool
    {
        return $user->hasPermission('reports.export');
    }

    public function viewAlert(User $user): bool
    {
        return $user->hasPermission('alerts.view');
    }

    public function manageAlert(User $user): bool
    {
        return $user->hasPermission('alerts.manage');
    }

    public function viewDashboardKey(User $user, string $key): bool
    {
        return $user->hasPermission('dashboard.'.$key.'.view');
    }
}
