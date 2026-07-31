<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Domain\Delivery\DeliveryStatus;
use App\Domain\Identity\UserRole;
use App\Models\Customer;
use App\Models\Delivery;
use App\Models\Kitchen;
use App\Models\User;
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
        return view('dashboard.owner', $this->officeStats());
    }

    public function staff(): View
    {
        return view('dashboard.staff', $this->officeStats());
    }

    /**
     * Compute the shared owner/staff dashboard statistics and recent
     * activity feed. Kept private so it does not leak into route matrix
     * expectations. Read-only aggregate queries only.
     *
     * @return array{stats: array<string, int>, recent_deliveries: \Illuminate\Support\Collection<int, \App\Models\Delivery>}
     */
    private function officeStats(): array
    {
        $stats = [
            'kitchens_total' => Kitchen::query()->count(),
            'kitchens_active' => Kitchen::query()->where('is_active', true)->count(),
            'customers_total' => Customer::query()->count(),
            'customers_active' => Customer::query()->where('is_active', true)->count(),
            'deliveries_in_progress' => Delivery::query()->whereIn('status', [
                DeliveryStatus::Scheduled->value,
                DeliveryStatus::InTransit->value,
            ])->count(),
            'deliveries_this_week' => Delivery::query()
                ->where('status', DeliveryStatus::Delivered->value)
                ->where('delivered_at', '>=', now()->subDays(7))
                ->count(),
        ];

        $recentDeliveries = Delivery::query()
            ->with(['kitchen', 'customer'])
            ->orderByDesc('created_at')
            ->limit(5)
            ->get();

        return [
            'stats' => $stats,
            'recent_deliveries' => $recentDeliveries,
        ];
    }

    /**
     * Render the courier dashboard.
     *
     * Loads the single active delivery assigned to the current courier
     * (scheduled or in_transit) via the `activeForCourier` scope on the
     * Delivery model. Under the AR-34 default cap of 1, a courier has
     * at most one active delivery at any time; the scope orders by
     * scheduled_at so the earliest is picked deterministically if the
     * cap is ever raised.
     */
    public function courier(): View
    {
        /** @var User $user */
        $user = Auth::guard('web')->user();

        $activeDelivery = Delivery::query()
            ->activeForCourier((int) $user->getKey())
            ->with(['kitchen', 'customer'])
            ->orderBy('scheduled_at')
            ->first();

        return view('dashboard.courier', [
            'activeDelivery' => $activeDelivery,
        ]);
    }
}
