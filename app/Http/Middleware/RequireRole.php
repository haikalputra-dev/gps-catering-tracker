<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Domain\Identity\UserRole;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use ValueError;

/**
 * Route middleware that limits access to a specific set of roles.
 *
 * Usage: ->middleware('role:owner') or ->middleware('role:staff,courier').
 * The owner role is NOT granted implicit access to other role's dashboards.
 */
class RequireRole
{
    public function handle(Request $request, Closure $next, string ...$allowed): Response
    {
        $user = Auth::guard('web')->user();
        if ($user === null) {
            throw new AccessDeniedHttpException();
        }

        if ($allowed === []) {
            throw new AccessDeniedHttpException();
        }

        $allowedRoles = [];
        foreach ($allowed as $value) {
            try {
                $allowedRoles[] = UserRole::from($value);
            } catch (ValueError) {
                // Invalid middleware parameter -> deny access rather than crash.
                throw new AccessDeniedHttpException();
            }
        }

        if (! in_array($user->role, $allowedRoles, true)) {
            throw new AccessDeniedHttpException();
        }

        return $next($request);
    }
}
