<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\User;

final class ProcurementPolicy
{
    public function view(User $user): bool
    {
        return $user->hasPermission('procurement.view');
    }

    public function manage(User $user): bool
    {
        return $user->hasPermission('procurement.manage');
    }

    public function approve(User $user): bool
    {
        return $user->hasPermission('procurement.approve');
    }
}
