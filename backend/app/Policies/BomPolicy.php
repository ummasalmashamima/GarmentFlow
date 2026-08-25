<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\User;

final class BomPolicy
{
    public function view(User $user): bool
    {
        return $user->hasPermission('bom.view');
    }

    public function manage(User $user): bool
    {
        return $user->hasPermission('bom.manage');
    }
}
