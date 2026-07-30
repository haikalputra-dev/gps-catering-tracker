<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Domain\Identity\UserRole;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

class DashboardController extends Controller
{
    /**
     * Dispatch the current user to the dashboard that matches their role.
     * Fails safely for unknown roles.
     */
    public function index(Request $request): RedirectResponse
    {
        $user = Auth::guard('web')->user();
        if ($user === null) {
            throw new AccessDeniedHttpException();
        }

        return match ($user->role) {
            UserRole::Owner => redirect()->route('owner.dashboard'),
            UserRole::Staff => redirect()->route('staff.dashboard'),
            UserRole::Courier => redirect()->route('courier.dashboard'),
            default => throw new AccessDeniedHttpException(),
        };
    }

    public function owner(): View
    {
        return view('dashboard.owner');
    }

    public function staff(): View
    {
        return view('dashboard.staff');
    }

    public function courier(): View
    {
        return view('dashboard.courier');
    }
}
