<?php

use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\CustomerController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\KitchenController;
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
});
