<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\Response;

final class EnsurePermission
{
    public function handle(Request $request, Closure $next, string $permission): Response
    {
        $user = $request->user();

        abort_unless(
            $user !== null
                && $user->tokenCan($permission)
                && Gate::forUser($user)->allows($permission),
            403,
            'You are not authorized for this action.',
        );

        return $next($request);
    }
}
