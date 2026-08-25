<?php

declare(strict_types=1);

namespace App\Services\Auth;

use App\Models\User;
use Illuminate\Support\Facades\Hash;

final class AuthService
{
    /**
     * Authenticate a user and issue a token scoped to the user's permissions.
     *
     * @return array{user: User, token: string}|null
     */
    public function authenticate(string $email, string $password): ?array
    {
        $user = User::query()
            ->where('email', mb_strtolower(trim($email)))
            ->first();

        if ($user === null || ! Hash::check($password, $user->password)) {
            return null;
        }

        $abilities = ['dashboard.view'];

        foreach (['master-data.view', 'master-data.manage', 'bom.view', 'bom.manage', 'buyer-order.view', 'buyer-order.manage', 'buyer-order.approve', 'buyer-order.confirm', 'planning.view', 'planning.manage', 'procurement.view', 'procurement.manage', 'procurement.approve', 'inventory.view', 'inventory.manage', 'inventory.adjust', 'production.view', 'production.manage', 'production.approve', 'production.override', 'sales.view', 'sales.manage', 'sales.confirm', 'sales.override', 'delivery.view', 'delivery.manage', 'delivery.dispatch', 'delivery.override', 'finance.view', 'finance.manage', 'finance.pay', 'finance.override', 'reports.view', 'reports.export', 'alerts.view', 'alerts.manage', 'dashboard.executive.view', 'dashboard.supply-chain.view', 'dashboard.production.view', 'dashboard.procurement.view', 'dashboard.warehouse.view',
        ] as $permission) {
            if ($user->hasPermission($permission)) {
                $abilities[] = $permission;
            }
        }

        $token = $user->createToken('garmentflow-web', $abilities);

        return [
            'user' => $user->load(['department', 'roles.permissions']),
            'token' => $token->plainTextToken,
        ];
    }

    public function revokeCurrentToken(User $user): void
    {
        $user->currentAccessToken()?->delete();
    }
}
