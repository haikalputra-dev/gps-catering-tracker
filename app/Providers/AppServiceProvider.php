<?php

namespace App\Providers;

use App\Models\Device;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Named limiter for the telemetry ingest endpoint. Keys on the
        // authenticated device attached by `AuthenticateDeviceToken` so
        // one misbehaving device cannot exhaust the quota of a
        // co-located device sharing the same egress IP (AR-49).
        //
        // Registered here (not in bootstrap/app.php's `withRouting(then:)`)
        // because the `then:` closure is skipped when routes are cached
        // via `php artisan route:cache`, which breaks the endpoint in
        // production. `boot()` always runs.
        RateLimiter::for('telemetry', function (Request $request): Limit {
            /** @var Device|null $device */
            $device = $request->attributes->get('device');

            $key = $device instanceof Device
                ? 'device:'.$device->id
                : 'ip:'.$request->ip();

            $perMinute = (int) config('telemetry.max_submissions_per_minute', 60);

            return Limit::perMinute(max(1, $perMinute))->by($key);
        });
    }
}
