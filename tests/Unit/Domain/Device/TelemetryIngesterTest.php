<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Device;

use App\Domain\Device\DeviceAssignmentService;
use App\Domain\Device\TelemetryIngester;
use App\Domain\Device\TelemetryPayload;
use App\Models\Delivery;
use App\Models\Device;
use App\Models\TelemetryRecord;
use App\Models\User;
use DateTimeImmutable;
use DateTimeZone;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class TelemetryIngesterTest extends TestCase
{
    use RefreshDatabase;

    private function ingester(): TelemetryIngester
    {
        return app(TelemetryIngester::class);
    }

    private function payload(?DateTimeImmutable $ts = null): TelemetryPayload
    {
        return new TelemetryPayload(
            latitude: -6.2000000,
            longitude: 106.8166667,
            gpsTimestamp: $ts ?? new DateTimeImmutable('2026-07-30T10:00:00+07:00'),
            speedKmh: 42.5,
            headingDegrees: 90.0,
        );
    }

    public function test_persists_record_when_courier_has_active_delivery(): void
    {
        [$device, $courier] = $this->assignedDeviceAndCourier();
        $delivery = Delivery::factory()->inTransit()->create([
            'courier_id' => $courier->id,
        ]);

        $record = $this->ingester()->ingest($device, $this->payload());

        $this->assertInstanceOf(TelemetryRecord::class, $record);
        $this->assertSame($device->id, $record->device_id);
        $this->assertSame($delivery->id, $record->delivery_id);
        $this->assertNotNull($record->received_at);
        $this->assertSame(1, TelemetryRecord::query()->count());
    }

    public function test_normalises_gps_timestamp_to_utc(): void
    {
        [$device, $courier] = $this->assignedDeviceAndCourier();
        Delivery::factory()->inTransit()->create(['courier_id' => $courier->id]);

        $local = new DateTimeImmutable('2026-07-30T17:00:00+07:00');
        $record = $this->ingester()->ingest($device, $this->payload($local));

        $this->assertNotNull($record);
        // We assert on the raw stored value: Eloquent's datetime cast
        // re-hydrates naive DB strings in the app timezone, so any
        // round-trip through the cast would defeat the point of this
        // check. The ingester's contract is: the string written to
        // disk is the UTC wall-clock of the client-supplied moment.
        $this->assertSame(
            '2026-07-30 10:00:00',
            (string) $record->getRawOriginal('gps_timestamp'),
        );
    }

    public function test_updates_last_seen_at_on_every_accepted_call(): void
    {
        $device = Device::factory()->create(['last_seen_at' => null]);

        $this->ingester()->ingest($device, $this->payload());

        $device->refresh();
        $this->assertNotNull($device->last_seen_at);
    }

    public function test_discards_when_device_has_no_open_assignment(): void
    {
        $device = Device::factory()->create();

        $result = $this->ingester()->ingest($device, $this->payload());

        $this->assertNull($result);
        $this->assertSame(0, TelemetryRecord::query()->count());
        $device->refresh();
        $this->assertNotNull($device->last_seen_at, 'last_seen_at still bumped on discard');
    }

    public function test_discards_when_assigned_courier_has_no_active_delivery(): void
    {
        [$device] = $this->assignedDeviceAndCourier();

        $result = $this->ingester()->ingest($device, $this->payload());

        $this->assertNull($result);
        $this->assertSame(0, TelemetryRecord::query()->count());
    }

    public function test_discards_when_courier_has_only_delivered_delivery(): void
    {
        [$device, $courier] = $this->assignedDeviceAndCourier();
        Delivery::factory()->delivered()->create(['courier_id' => $courier->id]);

        $result = $this->ingester()->ingest($device, $this->payload());

        $this->assertNull($result);
    }

    public function test_selects_most_recently_dispatched_active_delivery_when_multiple_present(): void
    {
        [$device, $courier] = $this->assignedDeviceAndCourier();

        $older = Delivery::factory()->inTransit()->create([
            'courier_id' => $courier->id,
            'dispatched_at' => Carbon::parse('2026-07-30 08:00:00'),
        ]);
        $newer = Delivery::factory()->inTransit()->create([
            'courier_id' => $courier->id,
            'dispatched_at' => Carbon::parse('2026-07-30 09:00:00'),
        ]);

        $record = $this->ingester()->ingest($device, $this->payload());

        $this->assertNotNull($record);
        $this->assertSame($newer->id, $record->delivery_id);
        $this->assertNotSame($older->id, $record->delivery_id);
    }

    public function test_scheduled_delivery_is_active_and_receives_telemetry(): void
    {
        [$device, $courier] = $this->assignedDeviceAndCourier();
        $delivery = Delivery::factory()->scheduled()->create([
            'courier_id' => $courier->id,
        ]);

        $record = $this->ingester()->ingest($device, $this->payload());

        $this->assertNotNull($record);
        $this->assertSame($delivery->id, $record->delivery_id);
    }

    /**
     * @return array{0: Device, 1: User}
     */
    private function assignedDeviceAndCourier(): array
    {
        $device = Device::factory()->create();
        $courier = User::factory()->courier()->create();
        $owner = User::factory()->owner()->create();
        app(DeviceAssignmentService::class)->assign($device, $courier, $owner);

        return [$device->fresh(), $courier];
    }
}
