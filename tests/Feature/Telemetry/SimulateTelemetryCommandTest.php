<?php

declare(strict_types=1);

namespace Tests\Feature\Telemetry;

use App\Domain\Delivery\DeliveryStatus;
use App\Models\Delivery;
use App\Models\Device;
use App\Models\DeviceAssignment;
use App\Models\TelemetryRecord;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Feature tests for the `telemetry:simulate` Artisan command.
 *
 * Behaviours locked here:
 *   - Argument validation: missing device, bad interval/duration/jitter.
 *   - Domain preconditions: device must exist and be active; must have
 *     an open courier assignment; that courier must have a scheduled
 *     or in_transit delivery.
 *   - Dry-run mode issues no HTTP calls but still prints the tick plan.
 *   - Real (non dry-run) mode POSTs to `/api/telemetry` with the
 *     device's Bearer token, one request per tick, with the right
 *     payload shape (AR-54).
 *   - The command does not touch `telemetry_records` directly; all
 *     persistence flows through the HTTP ingest path.
 */
class SimulateTelemetryCommandTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Build a device + assignment + active delivery so the command
     * makes it into the loop. Coordinates default to two points ~1 km
     * apart so distance/bearing are non-zero.
     */
    private function seedActiveScenario(): array
    {
        $owner = User::factory()->owner()->create();
        $courier = User::factory()->courier()->create();
        $device = Device::factory()
            ->withToken('sim-token-abcdefghijklmnopqrstu')
            ->create(['identifier' => 'SIM-CATERER-01']);
        DeviceAssignment::factory()->create([
            'device_id' => $device->id,
            'courier_id' => $courier->id,
            'assigned_by_user_id' => $owner->id,
            'assigned_at' => now(),
            'unassigned_at' => null,
        ]);
        $delivery = Delivery::factory()->inTransit()->create([
            'courier_id' => $courier->id,
            'kitchen_latitude' => -6.2000000,
            'kitchen_longitude' => 106.8000000,
            'customer_latitude' => -6.2100000,
            'customer_longitude' => 106.8100000,
        ]);

        return compact('owner', 'courier', 'device', 'delivery');
    }

    public function test_missing_device_option_returns_invalid_status(): void
    {
        $this->artisan('telemetry:simulate')
            ->expectsOutputToContain('--device option is required')
            ->assertExitCode(2);
    }

    public function test_unknown_device_identifier_fails(): void
    {
        $this->artisan('telemetry:simulate', ['--device' => 'DOES-NOT-EXIST', '--dry-run' => true])
            ->expectsOutputToContain("Device 'DOES-NOT-EXIST' not found")
            ->assertExitCode(1);
    }

    public function test_inactive_device_fails(): void
    {
        Device::factory()->inactive()->create(['identifier' => 'INACTIVE-01']);

        $this->artisan('telemetry:simulate', ['--device' => 'INACTIVE-01', '--dry-run' => true])
            ->expectsOutputToContain("'INACTIVE-01' is inactive")
            ->assertExitCode(1);
    }

    public function test_device_without_assignment_fails(): void
    {
        Device::factory()->create(['identifier' => 'UNBOUND-01']);

        $this->artisan('telemetry:simulate', ['--device' => 'UNBOUND-01', '--dry-run' => true])
            ->expectsOutputToContain("'UNBOUND-01' has no open courier assignment")
            ->assertExitCode(1);
    }

    public function test_courier_without_active_delivery_fails(): void
    {
        $owner = User::factory()->owner()->create();
        $courier = User::factory()->courier()->create();
        $device = Device::factory()->create(['identifier' => 'IDLE-01']);
        DeviceAssignment::factory()->create([
            'device_id' => $device->id,
            'courier_id' => $courier->id,
            'assigned_by_user_id' => $owner->id,
            'unassigned_at' => null,
        ]);

        $this->artisan('telemetry:simulate', ['--device' => 'IDLE-01', '--dry-run' => true])
            ->expectsOutputToContain('no scheduled or in_transit delivery')
            ->assertExitCode(1);
    }

    public function test_delivered_deliveries_are_ignored_when_selecting_active(): void
    {
        [$data] = [$this->seedActiveScenario()];
        // Flip the seeded in_transit delivery to delivered; the command
        // must then complain that no active delivery exists.
        $delivery = $data['delivery'];
        $delivery->status = DeliveryStatus::Delivered;
        $delivery->delivered_at = now('UTC');
        $delivery->save();

        $this->artisan('telemetry:simulate', [
            '--device' => $data['device']->identifier,
            '--dry-run' => true,
        ])
            ->expectsOutputToContain('no scheduled or in_transit delivery')
            ->assertExitCode(1);
    }

    public function test_zero_interval_is_rejected(): void
    {
        $data = $this->seedActiveScenario();

        $this->artisan('telemetry:simulate', [
            '--device' => $data['device']->identifier,
            '--interval' => 0,
            '--dry-run' => true,
        ])
            ->expectsOutputToContain('--interval must be a positive integer')
            ->assertExitCode(2);
    }

    public function test_duration_shorter_than_interval_is_rejected(): void
    {
        $data = $this->seedActiveScenario();

        $this->artisan('telemetry:simulate', [
            '--device' => $data['device']->identifier,
            '--interval' => 10,
            '--duration' => 5,
            '--dry-run' => true,
        ])
            ->expectsOutputToContain('--duration must be at least as long as one --interval tick')
            ->assertExitCode(2);
    }

    public function test_negative_jitter_is_rejected(): void
    {
        $data = $this->seedActiveScenario();

        $this->artisan('telemetry:simulate', [
            '--device' => $data['device']->identifier,
            '--jitter' => -1,
            '--dry-run' => true,
        ])
            ->expectsOutputToContain('--jitter must be zero or positive')
            ->assertExitCode(2);
    }

    public function test_dry_run_prints_plan_and_makes_no_http_calls(): void
    {
        Http::preventStrayRequests();
        Http::fake();

        $data = $this->seedActiveScenario();

        $this->artisan('telemetry:simulate', [
            '--device' => $data['device']->identifier,
            '--interval' => 1,
            '--duration' => 3,
            '--dry-run' => true,
        ])
            ->expectsOutputToContain('[dry-run]')
            ->expectsOutputToContain('tick 01/3')
            ->assertExitCode(0);

        Http::assertNothingSent();
        $this->assertSame(0, TelemetryRecord::query()->count());
    }

    public function test_dry_run_reports_correct_endpoint_and_delivery(): void
    {
        Http::fake();
        $data = $this->seedActiveScenario();

        config()->set('telemetry.simulator_base_url', 'http://sim.test');

        $this->artisan('telemetry:simulate', [
            '--device' => $data['device']->identifier,
            '--interval' => 1,
            '--duration' => 2,
            '--dry-run' => true,
        ])
            ->expectsOutputToContain('endpoint: http://sim.test/api/telemetry')
            ->expectsOutputToContain('delivery #'.$data['delivery']->id)
            ->assertExitCode(0);
    }

    public function test_real_run_posts_one_request_per_tick_with_bearer_token(): void
    {
        Http::preventStrayRequests();
        Http::fake([
            '*/api/telemetry' => Http::response('', 204),
        ]);

        $data = $this->seedActiveScenario();
        config()->set('telemetry.simulator_base_url', 'http://sim.test');

        $this->artisan('telemetry:simulate', [
            '--device' => $data['device']->identifier,
            '--interval' => 1,
            '--duration' => 3,
        ])->assertExitCode(0);

        Http::assertSentCount(3);
        Http::assertSent(function ($request) use ($data): bool {
            return $request->url() === 'http://sim.test/api/telemetry'
                && $request->method() === 'POST'
                && $request->hasHeader('Authorization', 'Bearer '.$data['device']->api_token);
        });
    }

    public function test_real_run_sends_the_expected_payload_shape(): void
    {
        Http::fake([
            '*/api/telemetry' => Http::response('', 204),
        ]);

        $data = $this->seedActiveScenario();

        $this->artisan('telemetry:simulate', [
            '--device' => $data['device']->identifier,
            '--interval' => 1,
            '--duration' => 2,
            '--jitter' => 0,
        ])->assertExitCode(0);

        Http::assertSent(function ($request): bool {
            $body = $request->data();
            return array_key_exists('latitude', $body)
                && array_key_exists('longitude', $body)
                && array_key_exists('speed_kmh', $body)
                && array_key_exists('heading_degrees', $body)
                && array_key_exists('gps_timestamp', $body)
                && is_float($body['latitude'])
                && is_float($body['longitude'])
                && preg_match('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}Z$/', (string) $body['gps_timestamp']) === 1;
        });
    }

    public function test_real_run_treats_429_responses_as_throttled(): void
    {
        Http::fake([
            '*/api/telemetry' => Http::response(['message' => 'rate limit'], 429),
        ]);

        $data = $this->seedActiveScenario();

        $this->artisan('telemetry:simulate', [
            '--device' => $data['device']->identifier,
            '--interval' => 1,
            '--duration' => 2,
        ])
            ->expectsOutputToContain('throttled')
            ->assertExitCode(0);

        Http::assertSentCount(2);
    }

    public function test_first_and_last_tick_land_on_snapshot_endpoints(): void
    {
        Http::fake([
            '*/api/telemetry' => Http::response('', 204),
        ]);

        $data = $this->seedActiveScenario();

        $this->artisan('telemetry:simulate', [
            '--device' => $data['device']->identifier,
            '--interval' => 1,
            '--duration' => 3,
            '--jitter' => 0,
        ])->assertExitCode(0);

        $recorded = Http::recorded();
        $bodies = [];
        foreach ($recorded as $pair) {
            /** @var \Illuminate\Http\Client\Request $req */
            $req = $pair[0];
            $bodies[] = $req->data();
        }
        $this->assertCount(3, $bodies);

        $first = $bodies[0];
        $last = $bodies[array_key_last($bodies)];
        $this->assertEqualsWithDelta((float) $data['delivery']->kitchen_latitude, $first['latitude'], 1e-6);
        $this->assertEqualsWithDelta((float) $data['delivery']->kitchen_longitude, $first['longitude'], 1e-6);
        $this->assertEqualsWithDelta((float) $data['delivery']->customer_latitude, $last['latitude'], 1e-6);
        $this->assertEqualsWithDelta((float) $data['delivery']->customer_longitude, $last['longitude'], 1e-6);
    }
}
