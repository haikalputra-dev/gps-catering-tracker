<?php

use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\CustomerController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DeliveryController;
use App\Http\Controllers\DeliveryTelemetryController;
use App\Http\Controllers\Device\DeviceController;
use App\Http\Controllers\KitchenController;
use App\Http\Controllers\Owner\UserController as OwnerUserController;
use App\Http\Controllers\Telemetry\TelemetryController;
use App\Http\Controllers\TrackingController;
use App\Http\Controllers\TrackingTelemetryController;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;

// Public landing page (AR-60). Authenticated users go straight to
// their dashboard; guests get the marketing welcome view. The route
// is named `welcome` so blade templates and tests can reference it
// without hard-coding the URL.
Route::get('/', function () {
    return Auth::guard('web')->check()
        ? redirect()->route('dashboard')
        : view('welcome');
})->name('welcome');

Route::middleware('guest')->group(function (): void {
    Route::get('/login', [AuthenticatedSessionController::class, 'create'])->name('login');
    Route::post('/login', [AuthenticatedSessionController::class, 'store'])->name('login.attempt');
});

Route::post('/logout', [AuthenticatedSessionController::class, 'destroy'])
    ->middleware(['auth', 'active'])
    ->name('logout');

// Customer-facing delivery tracking (Packet 10). Fully public: no
// customer accounts, no SMS/OTP, no signed URLs. Session-scoped after
// receipt + phone-last-4 authentication. The POST endpoint is
// throttled to 10 attempts per 15 minutes per IP+receipt (AR-42
// revised).
Route::get('/track', [TrackingController::class, 'form'])->name('tracking.form');
Route::post('/track', [TrackingController::class, 'authenticate'])
    ->middleware('throttle:10,15')
    ->name('tracking.authenticate');
Route::get('/track/status', [TrackingController::class, 'status'])->name('tracking.status');
Route::post('/track/sign-out', [TrackingController::class, 'signOut'])->name('tracking.signOut');

// Customer live-map polling endpoint (Packet 12, AR-57). Session-scoped
// authentication is handled inside the controller so an expired tab
// gets a JSON 401 rather than an HTML redirect. The `throttle:60,1`
// middleware caps a single client at one poll per second on average.
Route::get('/track/telemetry/latest', [TrackingTelemetryController::class, 'latest'])
    ->middleware('throttle:60,1')
    ->name('tracking.telemetry.latest');

Route::middleware(['auth', 'active', 'no.cache'])->group(function (): void {
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');

    Route::get('/owner/dashboard', [DashboardController::class, 'owner'])
        ->middleware('role:owner')
        ->name('owner.dashboard');

    Route::get('/staff/dashboard', [DashboardController::class, 'staff'])
        ->middleware('role:staff')
        ->name('staff.dashboard');

    Route::get('/courier/dashboard', [DashboardController::class, 'courier'])
        ->middleware('role:courier')
        ->name('courier.dashboard');

    Route::middleware('role:owner')->prefix('owner/users')->name('owner.users.')->group(function (): void {
        Route::get('/', [OwnerUserController::class, 'index'])->name('index');
        Route::get('/create', [OwnerUserController::class, 'create'])->name('create');
        Route::post('/', [OwnerUserController::class, 'store'])->name('store');
        Route::get('/{user}/edit', [OwnerUserController::class, 'edit'])->name('edit');
        Route::put('/{user}', [OwnerUserController::class, 'update'])->name('update');
    });

    // Owner-only physical device registration and binding management.
    // Devices are never deleted (AR-50); deactivation is the disable
    // path. Token rotation and courier assignment are separate POST
    // actions rather than being folded into `update` so audit intent
    // is unambiguous.
    Route::middleware('role:owner')->prefix('devices')->name('devices.')->group(function (): void {
        Route::get('/', [DeviceController::class, 'index'])->name('index');
        Route::get('/create', [DeviceController::class, 'create'])->name('create');
        Route::post('/', [DeviceController::class, 'store'])->name('store');
        Route::get('/{device}', [DeviceController::class, 'show'])->name('show');
        Route::get('/{device}/edit', [DeviceController::class, 'edit'])->name('edit');
        Route::put('/{device}', [DeviceController::class, 'update'])->name('update');
        Route::post('/{device}/rotate-token', [DeviceController::class, 'rotateToken'])->name('rotate-token');
        Route::post('/{device}/assign', [DeviceController::class, 'assign'])->name('assign');
        Route::post('/{device}/unassign', [DeviceController::class, 'unassign'])->name('unassign');
    });

    Route::middleware('role:owner,staff')->prefix('kitchens')->name('kitchens.')->group(function (): void {
        Route::get('/', [KitchenController::class, 'index'])->name('index');
        Route::get('/create', [KitchenController::class, 'create'])->name('create');
        Route::post('/', [KitchenController::class, 'store'])->name('store');
        Route::get('/{kitchen}/edit', [KitchenController::class, 'edit'])->name('edit');
        Route::put('/{kitchen}', [KitchenController::class, 'update'])->name('update');
    });

    Route::middleware('role:owner,staff')->prefix('customers')->name('customers.')->group(function (): void {
        Route::get('/', [CustomerController::class, 'index'])->name('index');
        Route::get('/create', [CustomerController::class, 'create'])->name('create');
        Route::post('/', [CustomerController::class, 'store'])->name('store');
        Route::get('/{customer}/edit', [CustomerController::class, 'edit'])->name('edit');
        Route::put('/{customer}', [CustomerController::class, 'update'])->name('update');
    });

    Route::prefix('deliveries')->name('deliveries.')->group(function (): void {
        // Owner and staff manage the delivery lifecycle up to scheduling.
        // Couriers cannot list, create, edit, or schedule deliveries.
        Route::middleware('role:owner,staff')->group(function (): void {
            Route::get('/', [DeliveryController::class, 'index'])->name('index');
            Route::get('/create', [DeliveryController::class, 'create'])->name('create');
            Route::post('/', [DeliveryController::class, 'store'])->name('store');
            Route::get('/{delivery}/edit', [DeliveryController::class, 'edit'])->name('edit');
            Route::put('/{delivery}', [DeliveryController::class, 'update'])->name('update');
            Route::post('/{delivery}/schedule', [DeliveryController::class, 'schedule'])->name('schedule');
        });

        // Show and cancel are shared by owner, staff, and the assigned
        // courier. The controller enforces courier-ownership on show,
        // and CancelDeliveryRequest enforces the AR-38 revised rule
        // (couriers may cancel only their own in_transit delivery).
        Route::middleware('role:owner,staff,courier')->group(function (): void {
            Route::get('/{delivery}', [DeliveryController::class, 'show'])->name('show');
            Route::post('/{delivery}/cancel', [DeliveryController::class, 'cancel'])->name('cancel');
        });

        // Dispatch and mark-delivered are courier-only. The domain
        // services enforce state-machine and actor-identity invariants
        // beyond the role check.
        Route::middleware('role:courier')->group(function (): void {
            Route::post('/{delivery}/dispatch', [DeliveryController::class, 'dispatch'])->name('dispatch');
            Route::post('/{delivery}/mark-delivered', [DeliveryController::class, 'markDelivered'])->name('mark-delivered');
        });

        // Live-map polling endpoint for the internal surface (Packet 12,
        // AR-57). Shares the same role trio as `show`; couriers are
        // additionally restricted to their own delivery inside the
        // controller. Throttle caps polling at 60 req/min per session
        // to leave headroom above the 3s default cadence.
        Route::middleware('role:owner,staff,courier')
            ->group(function (): void {
                Route::get('/{delivery}/telemetry/latest', [DeliveryTelemetryController::class, 'latest'])
                    ->middleware('throttle:60,1')
                    ->name('telemetry.latest');
            });
    });
});

// Device-authenticated telemetry ingestion (AR-49, AR-52). No web
// session is involved: the `device.auth` middleware resolves the
// Bearer token from `Authorization` and attaches the Device to the
// request; the named `telemetry` rate limiter keys off that same
// Device so per-device quotas are enforced independently of client
// IP. Exceptions bubble as JSON because `bootstrap/app.php` matches
// on `api/*`.
Route::post('/api/telemetry', [TelemetryController::class, 'store'])
    ->middleware(['device.auth', 'throttle:telemetry'])
    ->name('api.telemetry.store');
