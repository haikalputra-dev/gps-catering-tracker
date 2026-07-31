<?php

use App\Models\Device;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
        then: function (): void {
            // Named limiter for the telemetry ingest endpoint. Keys on the
            // authenticated device attached by `AuthenticateDeviceToken` so
            // one misbehaving device cannot exhaust the quota of a
            // co-located device sharing the same egress IP (AR-49).
            RateLimiter::for('telemetry', function (Request $request): Limit {
                /** @var Device|null $device */
                $device = $request->attributes->get('device');

                $key = $device instanceof Device
                    ? 'device:'.$device->id
                    : 'ip:'.$request->ip();

                $perMinute = (int) config('telemetry.max_submissions_per_minute', 60);

                return Limit::perMinute(max(1, $perMinute))->by($key);
            });
        },
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->trustProxies(at: '127.0.0.1');
        $middleware->alias([
            'active' => \App\Http\Middleware\EnsureUserIsActive::class,
            'role' => \App\Http\Middleware\RequireRole::class,
            'device.auth' => \App\Http\Middleware\AuthenticateDeviceToken::class,
            'no.cache' => \App\Http\Middleware\PreventBackButtonCache::class,
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
