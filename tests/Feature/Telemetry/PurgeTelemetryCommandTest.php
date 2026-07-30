<?php

declare(strict_types=1);

namespace Tests\Feature\Telemetry;

use App\Models\Delivery;
use App\Models\Device;
use App\Models\DeviceAssignment;
use App\Models\TelemetryRecord;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Feature tests for the `telemetry:purge` Artisan command.
 *
 * Behaviours locked here (Packet 13, AR-48):
 *   - Default `--dry-run` reports the target count and deletes nothing.
 *   - A normal run deletes only rows whose `received_at` is older than
 *     the configured retention window and leaves newer rows in place.
 *   - Empty table and zero-match runs report clearly and succeed.
 *   - Runtime overrides of `telemetry.retention_days` are honoured.
 *   - Zero or negative retention_days is treated as misconfiguration
 *     and returns a non-zero exit code.
 *   - `devices` and `device_assignments` rows are NEVER touched.
 */
class PurgeTelemetryCommandTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Build a device + assignment + active delivery so telemetry rows
     * satisfy foreign-key constraints, then insert rows at explicit
     * `received_at` timestamps.
     *
     * Returns a tuple of ['device' => Device, 'delivery' => Delivery]
     * so tests can assert those parent rows are preserved.
     *
     * @param  array<int, array{received_at: CarbonImmutable}>  $rows
     * @return array{device: Device, delivery: Delivery, records: array<int, TelemetryRecord>}
     */
    private function seedRows(array $rows): array
    {
        $owner = User::factory()->owner()->create();
        $courier = User::factory()->courier()->create();
        $device = Device::factory()->create();
        DeviceAssignment::factory()->create([
            'device_id' => $device->id,
            'courier_id' => $courier->id,
            'assigned_by_user_id' => $owner->id,
            'assigned_at' => now(),
            'unassigned_at' => null,
        ]);
        $delivery = Delivery::factory()->inTransit()->create([
            'courier_id' => $courier->id,
        ]);

        $records = [];
        foreach ($rows as $row) {
            $records[] = TelemetryRecord::factory()->create([
                'device_id' => $device->id,
                'delivery_id' => $delivery->id,
                'received_at' => $row['received_at'],
                'gps_timestamp' => $row['received_at']->subSeconds(2),
            ]);
        }

        return ['device' => $device, 'delivery' => $delivery, 'records' => $records];
    }

    public function test_dry_run_reports_target_count_and_deletes_nothing(): void
    {
        $now = CarbonImmutable::now('UTC');
        $seed = $this->seedRows(array_fill(0, 5, ['received_at' => $now->subDays(45)]));

        $this->artisan('telemetry:purge', ['--dry-run' => true])
            ->expectsOutputToContain('Dry run: would delete 5 telemetry rows')
            ->assertExitCode(0);

        $this->assertSame(5, TelemetryRecord::query()->count());
        $this->assertDatabaseHas('devices', ['id' => $seed['device']->id]);
        $this->assertDatabaseHas('deliveries', ['id' => $seed['delivery']->id]);
    }

    public function test_normal_run_deletes_all_stale_rows(): void
    {
        $now = CarbonImmutable::now('UTC');
        $seed = $this->seedRows(array_fill(0, 5, ['received_at' => $now->subDays(45)]));

        $this->artisan('telemetry:purge')
            ->expectsOutputToContain('Deleted 5 telemetry rows')
            ->assertExitCode(0);

        $this->assertSame(0, TelemetryRecord::query()->count());
        $this->assertDatabaseHas('devices', ['id' => $seed['device']->id]);
        $this->assertDatabaseHas('device_assignments', ['device_id' => $seed['device']->id]);
        $this->assertDatabaseHas('deliveries', ['id' => $seed['delivery']->id]);
    }

    public function test_mixed_ages_deletes_only_stale_rows(): void
    {
        $now = CarbonImmutable::now('UTC');
        $this->seedRows([
            ['received_at' => $now->subDays(45)],
            ['received_at' => $now->subDays(40)],
            ['received_at' => $now->subDays(31)],
            ['received_at' => $now->subDays(5)],
            ['received_at' => $now->subHours(1)],
        ]);

        $this->artisan('telemetry:purge')
            ->expectsOutputToContain('Deleted 3 telemetry rows')
            ->assertExitCode(0);

        $this->assertSame(2, TelemetryRecord::query()->count());
        $this->assertSame(
            0,
            TelemetryRecord::query()
                ->where('received_at', '<', $now->subDays(30))
                ->count(),
        );
    }

    public function test_empty_table_reports_nothing_to_delete(): void
    {
        $this->assertSame(0, TelemetryRecord::query()->count());

        $this->artisan('telemetry:purge')
            ->expectsOutputToContain('No telemetry rows older than')
            ->assertExitCode(0);
    }

    public function test_all_rows_recent_reports_nothing_to_delete(): void
    {
        $now = CarbonImmutable::now('UTC');
        $this->seedRows([
            ['received_at' => $now->subDays(1)],
            ['received_at' => $now->subDays(10)],
            ['received_at' => $now->subDays(29)->subHours(23)],
        ]);

        $this->artisan('telemetry:purge')
            ->expectsOutputToContain('No telemetry rows older than')
            ->assertExitCode(0);

        $this->assertSame(3, TelemetryRecord::query()->count());
    }

    public function test_runtime_retention_override_is_honoured(): void
    {
        config()->set('telemetry.retention_days', 7);

        $now = CarbonImmutable::now('UTC');
        $this->seedRows([
            ['received_at' => $now->subDays(10)],
            ['received_at' => $now->subDays(8)],
            ['received_at' => $now->subDays(6)],
            ['received_at' => $now->subDays(1)],
        ]);

        $this->artisan('telemetry:purge')
            ->expectsOutputToContain('7 day retention')
            ->assertExitCode(0);

        $this->assertSame(2, TelemetryRecord::query()->count());
    }

    public function test_zero_retention_days_returns_failure(): void
    {
        config()->set('telemetry.retention_days', 0);

        $this->artisan('telemetry:purge')
            ->expectsOutputToContain('telemetry.retention_days is misconfigured')
            ->assertExitCode(1);
    }

    public function test_negative_retention_days_returns_failure(): void
    {
        config()->set('telemetry.retention_days', -1);

        $this->artisan('telemetry:purge')
            ->expectsOutputToContain('telemetry.retention_days is misconfigured')
            ->assertExitCode(1);
    }
}
