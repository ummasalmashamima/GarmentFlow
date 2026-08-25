<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\User;

final class ProductionPolicy
{
    public function view(User $user): bool
    {
        return $user->hasPermission('production.view');
    }

    public function manage(User $user): bool
    {
        return $user->hasPermission('production.manage');
    }

    public function approve(User $user): bool
    {
        return $user->hasPermission('production.approve');
    }

    public function override(User $user): bool
    {
        return $user->hasPermission('production.override');
    }
}
