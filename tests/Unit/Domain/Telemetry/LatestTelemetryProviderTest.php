<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Telemetry;

use App\Domain\Telemetry\LatestTelemetryProvider;
use App\Models\Delivery;
use App\Models\Device;
use App\Models\TelemetryRecord;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * Unit test for {@see LatestTelemetryProvider}. Focuses on the shape
 * of the returned array and the projection differences between the
 * staff and customer surfaces (AR-57).
 */
class LatestTelemetryProviderTest extends TestCase
{
    use RefreshDatabase;

    public function test_staff_surface_returns_full_row_when_records_exist(): void
    {
        $delivery = Delivery::factory()->inTransit()->create();
        $device = Device::factory()->create();
        $gpsTs = Carbon::parse('2026-07-30 12:00:00', 'UTC');
        $receivedTs = Carbon::parse('2026-07-30 12:00:05', 'UTC');

        TelemetryRecord::factory()->create([
            'device_id' => $device->id,
            'delivery_id' => $delivery->id,
            'latitude' => -6.2001234,
            'longitude' => 106.8165432,
            'speed_kmh' => 33.42,
            'heading_degrees' => 128.75,
            'gps_timestamp' => $gpsTs,
            'received_at' => $receivedTs,
        ]);

        $out = app(LatestTelemetryProvider::class)->forStaff($delivery);

        $this->assertSame($delivery->id, $out['delivery_id']);
        $this->assertSame('in_transit', $out['status']);
        $this->assertNotNull($out['latest']);
        $this->assertEqualsWithDelta(-6.2001234, $out['latest']['latitude'], 1e-6);
        $this->assertEqualsWithDelta(106.8165432, $out['latest']['longitude'], 1e-6);
        $this->assertEqualsWithDelta(33.42, $out['latest']['speed_kmh'], 1e-3);
        $this->assertEqualsWithDelta(128.75, $out['latest']['heading_degrees'], 1e-3);
        $this->assertSame('2026-07-30T12:00:00Z', $out['latest']['gps_timestamp']);
        $this->assertSame('2026-07-30T12:00:05Z', $out['latest']['received_at']);
    }

    public function test_staff_surface_returns_null_latest_when_no_records(): void
    {
        $delivery = Delivery::factory()->scheduled()->create();

        $out = app(LatestTelemetryProvider::class)->forStaff($delivery);

        $this->assertSame($delivery->id, $out['delivery_id']);
        $this->assertSame('scheduled', $out['status']);
        $this->assertNull($out['latest']);
    }

    public function test_staff_surface_picks_the_most_recent_record(): void
    {
        $delivery = Delivery::factory()->inTransit()->create();
        $device = Device::factory()->create();

        TelemetryRecord::factory()->create([
            'device_id' => $device->id,
            'delivery_id' => $delivery->id,
            'latitude' => -6.20,
            'longitude' => 106.80,
            'received_at' => Carbon::parse('2026-07-30 12:00:00', 'UTC'),
        ]);
        TelemetryRecord::factory()->create([
            'device_id' => $device->id,
            'delivery_id' => $delivery->id,
            'latitude' => -6.25,
            'longitude' => 106.85,
            'received_at' => Carbon::parse('2026-07-30 12:00:30', 'UTC'),
        ]);

        $out = app(LatestTelemetryProvider::class)->forStaff($delivery);

        $this->assertEqualsWithDelta(-6.25, $out['latest']['latitude'], 1e-6);
        $this->assertSame('2026-07-30T12:00:30Z', $out['latest']['received_at']);
    }

    public function test_customer_surface_omits_speed_and_heading(): void
    {
        $delivery = Delivery::factory()->inTransit()->create();
        $device = Device::factory()->create();
        TelemetryRecord::factory()->create([
            'device_id' => $device->id,
            'delivery_id' => $delivery->id,
            'latitude' => -6.20,
            'longitude' => 106.80,
            'speed_kmh' => 55.5,
            'heading_degrees' => 200.0,
            'received_at' => Carbon::parse('2026-07-30 12:00:00', 'UTC'),
        ]);

        $out = app(LatestTelemetryProvider::class)->forCustomer($delivery);

        $this->assertNotNull($out['latest']);
        $this->assertArrayHasKey('latitude', $out['latest']);
        $this->assertArrayHasKey('longitude', $out['latest']);
        $this->assertArrayHasKey('received_at', $out['latest']);
        $this->assertArrayNotHasKey('speed_kmh', $out['latest']);
        $this->assertArrayNotHasKey('heading_degrees', $out['latest']);
        $this->assertArrayNotHasKey('gps_timestamp', $out['latest']);
    }

    public function test_customer_surface_returns_null_when_not_in_transit(): void
    {
        $delivery = Delivery::factory()->scheduled()->create();
        $device = Device::factory()->create();
        TelemetryRecord::factory()->create([
            'device_id' => $device->id,
            'delivery_id' => $delivery->id,
        ]);

        $out = app(LatestTelemetryProvider::class)->forCustomer($delivery);

        $this->assertSame('scheduled', $out['status']);
        $this->assertNull($out['latest']);
    }

    public function test_customer_surface_returns_null_when_delivered(): void
    {
        $delivery = Delivery::factory()->inTransit()->create();
        $device = Device::factory()->create();
        TelemetryRecord::factory()->create([
            'device_id' => $device->id,
            'delivery_id' => $delivery->id,
        ]);

        // Flip to delivered without touching telemetry rows.
        $delivery->status = \App\Domain\Delivery\DeliveryStatus::Delivered;
        $delivery->delivered_at = Carbon::now('UTC');
        $delivery->save();
        $delivery->refresh();

        $out = app(LatestTelemetryProvider::class)->forCustomer($delivery);

        $this->assertSame('delivered', $out['status']);
        $this->assertNull($out['latest']);
    }
}
