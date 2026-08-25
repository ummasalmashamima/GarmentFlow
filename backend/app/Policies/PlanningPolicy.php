<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\User;

final class PlanningPolicy
{
    public function view(User $user): bool
    {
        return $user->hasPermission('planning.view');
    }

    public function manage(User $user): bool
    {
        return $user->hasPermission('planning.manage');
    }
}
