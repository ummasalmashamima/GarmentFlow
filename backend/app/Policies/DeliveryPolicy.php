<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Delivery;
use App\Models\User;

final class DeliveryPolicy
{
    public function view(User $user, ?Delivery $delivery = null): bool
    {
        return $user->hasPermission('delivery.view');
    }

    public function manage(User $user, ?Delivery $delivery = null): bool
    {
        return $user->hasPermission('delivery.manage');
    }

    public function dispatch(User $user, ?Delivery $delivery = null): bool
    {
        return $user->hasPermission('delivery.dispatch');
    }

    public function override(User $user, ?Delivery $delivery = null): bool
    {
        return $user->hasPermission('delivery.override');
    }
}
