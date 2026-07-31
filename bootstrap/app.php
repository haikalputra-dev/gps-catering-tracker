<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->trustProxies(at: '127.0.0.1');
        $middleware->alias([
            'active' => \App\Http\Middleware\EnsureUserIsActive::class,
            'role' => \App\Http\Middleware\RequireRole::class,
            'device.auth' => \App\Http\Middleware\AuthenticateDeviceToken::class,
            'no.cache' => \App\Http\Middleware\PreventBackButtonCache::class,
        ]);
        
        $middleware->validateCsrfTokens(except: [
            'api/*',
        ]);

        // Laravel's default middleware priority runs ThrottleRequests
        // before any non-prioritised middleware, which would evaluate
        // the `telemetry` limiter callback *before* `device.auth` has
        // resolved the Bearer token and attached the Device to the
        // request. That would silently degrade AR-49 keying from
        // per-device to per-IP. Explicitly declaring
        // `AuthenticateDeviceToken` as a higher priority guarantees
        // the correct order at every request.
        $middleware->prependToPriorityList(
            \Illuminate\Routing\Middleware\ThrottleRequests::class,
            \App\Http\Middleware\AuthenticateDeviceToken::class,
        );
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*'),
        );
    })->create();
