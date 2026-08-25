<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\SalesOrder;
use App\Models\User;

class SalesOrderPolicy
{
    public function view(User $user, ?SalesOrder $order = null): bool
    {
        return $user->hasPermission('sales.view');
    }

    public function manage(User $user, ?SalesOrder $order = null): bool
    {
        return $user->hasPermission('sales.manage');
    }

    public function confirm(User $user, ?SalesOrder $order = null): bool
    {
        return $user->hasPermission('sales.confirm');
    }

    public function override(User $user, ?SalesOrder $order = null): bool
    {
        return $user->hasPermission('sales.override');
    }
}
