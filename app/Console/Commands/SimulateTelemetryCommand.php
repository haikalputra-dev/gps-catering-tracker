<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Domain\Delivery\DeliveryStatus;
use App\Domain\Telemetry\PathInterpolator;
use App\Models\Delivery;
use App\Models\Device;
use App\Models\DeviceAssignment;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;

/**
 * `telemetry:simulate` — an ESP32 stand-in that drives the live-map
 * during development and QA (AR-54).
 *
 * The command locates the device by `identifier`, resolves its open
 * assignment to a courier, resolves that courier's single active
 * (`scheduled` or `in_transit`) delivery, then walks a straight line
 * between the delivery's kitchen and customer snapshot coordinates.
 * Each interpolated position is POSTed to
 * `POST /api/telemetry` on the configured base URL, using the
 * device's real Bearer token in the `Authorization` header.
 *
 * The command intentionally does NOT write to `telemetry_records`
 * directly: the whole point is to exercise the ingestion pipeline
 * (Bearer authentication, per-device rate limit, ingester's
 * accept-and-discard rule for idle devices). Failures at any layer
 * surface as HTTP status codes and are logged to the console.
 *
 * Options:
 *   --device=IDENT   required; matches `devices.identifier`
 *   --interval=N     seconds between pings; default from
 *                    `config('telemetry.simulator_default_interval_seconds')`
 *   --duration=N     total run duration in seconds; default from
 *                    `config('telemetry.simulator_default_duration_seconds')`
 *   --jitter=M       metres of positional jitter added per ping
 *                    (uniform +/- M); default from
 *                    `config('telemetry.simulator_default_jitter_meters')`
 *   --dry-run        compute the plan but issue no HTTP calls; useful
 *                    for tests and local previews
 */
class SimulateTelemetryCommand extends Command
{
    protected $signature = 'telemetry:simulate
        {--device= : Device identifier to impersonate (required)}
        {--interval= : Seconds between pings}
        {--duration= : Total run duration in seconds}
        {--jitter= : Metres of positional jitter per ping}
        {--dry-run : Do not issue HTTP calls; print the plan only}';

    protected $description = 'Drive the live-map by POSTing simulated GPS pings to /api/telemetry as a real device.';

    /**
     * Set to `true` when SIGINT / SIGTERM arrives so the main loop
     * can exit cleanly after the current tick. Signals are registered
     * only when the `pcntl` extension is present; without it, the
     * command still runs but honours only the natural duration cap.
     */
    private bool $shouldStop = false;

    public function handle(): int
    {
        $identifier = (string) ($this->option('device') ?? '');
        if ($identifier === '') {
            $this->error('The --device option is required.');
            return self::INVALID;
        }

        $interval = (int) ($this->option('interval')
            ?? (int) config('telemetry.simulator_default_interval_seconds', 2));
        $duration = (int) ($this->option('duration')
            ?? (int) config('telemetry.simulator_default_duration_seconds', 120));
        $jitterMeters = (float) ($this->option('jitter')
            ?? (float) config('telemetry.simulator_default_jitter_meters', 0.0));
        $dryRun = (bool) $this->option('dry-run');

        if ($interval < 1) {
            $this->error('The --interval must be a positive integer number of seconds.');
            return self::INVALID;
        }
        if ($duration < $interval) {
            $this->error('The --duration must be at least as long as one --interval tick.');
            return self::INVALID;
        }
        if ($jitterMeters < 0.0) {
            $this->error('The --jitter must be zero or positive (metres).');
            return self::INVALID;
        }

        $device = Device::query()
            ->where('identifier', $identifier)
            ->first();
        if ($device === null) {
            $this->error("Device '{$identifier}' not found.");
            return self::FAILURE;
        }
        if (! $device->isActive()) {
            $this->error("Device '{$identifier}' is inactive; activate it before simulating.");
            return self::FAILURE;
        }

        $assignment = DeviceAssignment::query()
            ->where('device_id', $device->getKey())
            ->whereNull('unassigned_at')
            ->latest('assigned_at')
            ->first();
        if ($assignment === null) {
            $this->error("Device '{$identifier}' has no open courier assignment.");
            return self::FAILURE;
        }

        $delivery = Delivery::query()
            ->where('courier_id', $assignment->courier_id)
            ->whereIn('status', [
                DeliveryStatus::Scheduled->value,
                DeliveryStatus::InTransit->value,
            ])
            ->orderBy('id')
            ->first();
        if ($delivery === null) {
            $this->error(
                "Courier bound to device '{$identifier}' has no scheduled or in_transit delivery."
            );
            return self::FAILURE;
        }

        return $this->simulate(
            $device,
            $delivery,
            $interval,
            $duration,
            $jitterMeters,
            $dryRun,
        );
    }

    /**
     * Main simulation loop. Kept separate from `handle` so the setup
     * validation stays readable.
     */
    private function simulate(
        Device $device,
        Delivery $delivery,
        int $interval,
        int $duration,
        float $jitterMeters,
        bool $dryRun,
    ): int {
        $this->installSignalHandlers();

        $startLat = (float) $delivery->kitchen_latitude;
        $startLng = (float) $delivery->kitchen_longitude;
        $endLat = (float) $delivery->customer_latitude;
        $endLng = (float) $delivery->customer_longitude;

        $totalMeters = PathInterpolator::distanceMeters(
            $startLat, $startLng, $endLat, $endLng,
        );
        $bearing = PathInterpolator::bearing(
            $startLat, $startLng, $endLat, $endLng,
        );

        $tickCount = intdiv($duration, $interval);
        if ($tickCount < 1) {
            $tickCount = 1;
        }

        $baseUrl = (string) config('telemetry.simulator_base_url', 'http://127.0.0.1:8000');
        $endpoint = rtrim($baseUrl, '/').'/api/telemetry';

        $this->info(sprintf(
            'Simulating device %s -> courier #%d -> delivery #%d (%s)',
            $device->identifier,
            (int) $delivery->courier_id,
            (int) $delivery->getKey(),
            $delivery->status->value,
        ));
        $this->line(sprintf(
            '  path: (%s, %s) -> (%s, %s), ~%.0f m',
            number_format($startLat, 7),
            number_format($startLng, 7),
            number_format($endLat, 7),
            number_format($endLng, 7),
            $totalMeters,
        ));
        $this->line(sprintf(
            '  ticks: %d, interval: %ds, duration: %ds, jitter: %.1f m%s',
            $tickCount,
            $interval,
            $duration,
            $jitterMeters,
            $dryRun ? ' [dry-run]' : '',
        ));
        $this->line("  endpoint: {$endpoint}");

        // Estimate an average km/h for the whole run so each ping can
        // report a plausible speed value. total_meters / total_seconds
        // -> m/s -> * 3.6 -> km/h. Zero-distance runs report zero.
        $avgSpeedKmh = $duration > 0
            ? ($totalMeters / $duration) * 3.6
            : 0.0;

        $ok = 0;
        $throttled = 0;
        $errored = 0;

        for ($i = 0; $i < $tickCount; $i++) {
            if ($this->shouldStop) {
                $this->warn('Received stop signal; halting after current tick.');
                break;
            }

            $t = $tickCount === 1 ? 1.0 : $i / ($tickCount - 1);
            [$lat, $lng] = PathInterpolator::interpolate(
                $startLat, $startLng, $endLat, $endLng, $t,
            );

            if ($jitterMeters > 0.0) {
                [$dLat, $dLng] = PathInterpolator::jitterOffsetDegrees(
                    $lat, $jitterMeters,
                );
                // Uniform +/-1 signed randomness on each axis.
                $lat += $dLat * (mt_rand(-1000, 1000) / 1000.0);
                $lng += $dLng * (mt_rand(-1000, 1000) / 1000.0);
            }

            $payload = [
                'latitude' => round($lat, 7),
                'longitude' => round($lng, 7),
                'speed_kmh' => round($avgSpeedKmh, 2),
                'heading_degrees' => round($bearing, 2),
                'gps_timestamp' => now('UTC')->format('Y-m-d\TH:i:s\Z'),
            ];

            if ($dryRun) {
                $this->line(sprintf(
                    '  tick %02d/%d t=%.3f  lat=%s lng=%s  speed=%.2f  heading=%.2f',
                    $i + 1,
                    $tickCount,
                    $t,
                    number_format($payload['latitude'], 7),
                    number_format($payload['longitude'], 7),
                    $payload['speed_kmh'],
                    $payload['heading_degrees'],
                ));
                $ok++;
            } else {
                $status = $this->postTelemetry($endpoint, (string) $device->api_token, $payload);
                if ($status === 204) {
                    $ok++;
                    $this->line(sprintf('  tick %02d/%d 204 accepted', $i + 1, $tickCount));
                } elseif ($status === 429) {
                    $throttled++;
                    $this->warn(sprintf('  tick %02d/%d 429 throttled', $i + 1, $tickCount));
                } else {
                    $errored++;
                    $this->error(sprintf('  tick %02d/%d HTTP %d', $i + 1, $tickCount, $status));
                }
            }

            if ($i < $tickCount - 1 && ! $this->shouldStop) {
                $this->sleepInterval($interval);
            }
        }

        $this->info(sprintf(
            'Done. accepted=%d throttled=%d errored=%d',
            $ok, $throttled, $errored,
        ));

        return self::SUCCESS;
    }

    /**
     * POST a single ping to the ingestion endpoint using the device's
     * real Bearer token. Returns the HTTP status code (0 when the
     * request could not be dispatched at all — treated as an error).
     */
    private function postTelemetry(string $endpoint, string $token, array $payload): int
    {
        try {
            $response = Http::acceptJson()
                ->withToken($token)
                ->timeout(5)
                ->post($endpoint, $payload);
        } catch (\Throwable $e) {
            $this->error('  transport error: '.$e->getMessage());
            return 0;
        }

        return (int) $response->status();
    }

    /**
     * Sleep between ticks. Dispatches pcntl signals mid-sleep on
     * platforms that support them so SIGINT halts the loop promptly.
     */
    private function sleepInterval(int $seconds): void
    {
        for ($s = 0; $s < $seconds; $s++) {
            if ($this->shouldStop) {
                return;
            }
            sleep(1);
            if (function_exists('pcntl_signal_dispatch')) {
                pcntl_signal_dispatch();
            }
        }
    }

    /**
     * Install pcntl signal handlers when the extension is available.
     * On platforms without pcntl (Windows or minimal PHP builds) the
     * command still runs but responds only to the natural duration cap.
     */
    private function installSignalHandlers(): void
    {
        if (! function_exists('pcntl_signal') || ! function_exists('pcntl_async_signals')) {
            return;
        }
        pcntl_async_signals(true);
        pcntl_signal(SIGINT, function (): void {
            $this->shouldStop = true;
        });
        pcntl_signal(SIGTERM, function (): void {
            $this->shouldStop = true;
        });
    }
}
