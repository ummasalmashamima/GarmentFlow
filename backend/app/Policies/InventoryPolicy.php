<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\User;

final class InventoryPolicy
{
    public function view(User $user): bool
    {
        return $user->hasPermission('inventory.view');
    }

    public function manage(User $user): bool
    {
        return $user->hasPermission('inventory.manage');
    }

    public function adjust(User $user): bool
    {
        return $user->hasPermission('inventory.adjust');
    }
}
