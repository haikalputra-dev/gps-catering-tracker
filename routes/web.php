<?php

use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\Owner\UserController as OwnerUserController;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return Auth::guard('web')->check()
        ? redirect()->route('dashboard')
        : redirect()->route('login');
});

Route::middleware('guest')->group(function (): void {
    Route::get('/login', [AuthenticatedSessionController::class, 'create'])->name('login');
    Route::post('/login', [AuthenticatedSessionController::class, 'store'])->name('login.attempt');
});

Route::post('/logout', [AuthenticatedSessionController::class, 'destroy'])
    ->middleware(['auth', 'active'])
    ->name('logout');

Route::middleware(['auth', 'active'])->group(function (): void {
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
});
