<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\User;

final class BuyerOrderPolicy
{
    public function view(User $user): bool
    {
        return $user->hasPermission('buyer-order.view');
    }

    public function manage(User $user): bool
    {
        return $user->hasPermission('buyer-order.manage');
    }

    public function approve(User $user): bool
    {
        return $user->hasPermission('buyer-order.approve');
    }

    public function confirm(User $user): bool
    {
        return $user->hasPermission('buyer-order.confirm');
    }
}
