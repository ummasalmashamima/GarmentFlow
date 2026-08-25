<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\User;

final class FinancePolicy
{
    public function view(User $user): bool
    {
        return $user->hasPermission('finance.view');
    }

    public function manage(User $user): bool
    {
        return $user->hasPermission('finance.manage');
    }

    public function pay(User $user): bool
    {
        return $user->hasPermission('finance.pay');
    }

    public function override(User $user): bool
    {
        return $user->hasPermission('finance.override');
    }
}
