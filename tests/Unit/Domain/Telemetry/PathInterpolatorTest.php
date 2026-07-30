<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Telemetry;

use App\Domain\Telemetry\PathInterpolator;
use PHPUnit\Framework\TestCase;

class PathInterpolatorTest extends TestCase
{
    public function test_interpolate_at_zero_returns_start(): void
    {
        [$lat, $lng] = PathInterpolator::interpolate(-6.20, 106.80, -6.30, 106.90, 0.0);
        $this->assertEqualsWithDelta(-6.20, $lat, 1e-9);
        $this->assertEqualsWithDelta(106.80, $lng, 1e-9);
    }

    public function test_interpolate_at_one_returns_end(): void
    {
        [$lat, $lng] = PathInterpolator::interpolate(-6.20, 106.80, -6.30, 106.90, 1.0);
        $this->assertEqualsWithDelta(-6.30, $lat, 1e-9);
        $this->assertEqualsWithDelta(106.90, $lng, 1e-9);
    }

    public function test_interpolate_at_half_returns_midpoint(): void
    {
        [$lat, $lng] = PathInterpolator::interpolate(-6.20, 106.80, -6.30, 106.90, 0.5);
        $this->assertEqualsWithDelta(-6.25, $lat, 1e-9);
        $this->assertEqualsWithDelta(106.85, $lng, 1e-9);
    }

    public function test_interpolate_clamps_t_below_zero(): void
    {
        [$lat, $lng] = PathInterpolator::interpolate(-6.20, 106.80, -6.30, 106.90, -0.5);
        $this->assertEqualsWithDelta(-6.20, $lat, 1e-9);
        $this->assertEqualsWithDelta(106.80, $lng, 1e-9);
    }

    public function test_interpolate_clamps_t_above_one(): void
    {
        [$lat, $lng] = PathInterpolator::interpolate(-6.20, 106.80, -6.30, 106.90, 1.5);
        $this->assertEqualsWithDelta(-6.30, $lat, 1e-9);
        $this->assertEqualsWithDelta(106.90, $lng, 1e-9);
    }

    public function test_bearing_east_is_approximately_ninety_degrees(): void
    {
        // Moving purely east from Jakarta.
        $b = PathInterpolator::bearing(-6.20, 106.80, -6.20, 107.00);
        $this->assertEqualsWithDelta(90.0, $b, 0.5);
    }

    public function test_bearing_north_is_approximately_zero_degrees(): void
    {
        $b = PathInterpolator::bearing(-6.20, 106.80, -6.10, 106.80);
        // Bearing may be 0 or ~360; both are equivalent.
        $normalised = $b > 180 ? 360.0 - $b : $b;
        $this->assertEqualsWithDelta(0.0, $normalised, 0.5);
    }

    public function test_bearing_south_is_approximately_one_eighty(): void
    {
        $b = PathInterpolator::bearing(-6.20, 106.80, -6.30, 106.80);
        $this->assertEqualsWithDelta(180.0, $b, 0.5);
    }

    public function test_bearing_zero_length_returns_zero(): void
    {
        $b = PathInterpolator::bearing(-6.20, 106.80, -6.20, 106.80);
        $this->assertSame(0.0, $b);
    }

    public function test_distance_meters_zero_length_returns_zero(): void
    {
        $d = PathInterpolator::distanceMeters(-6.20, 106.80, -6.20, 106.80);
        $this->assertEqualsWithDelta(0.0, $d, 1e-6);
    }

    public function test_distance_meters_matches_expected_scale(): void
    {
        // ~0.01 degrees latitude ~= 1.1 km near the equator.
        $d = PathInterpolator::distanceMeters(-6.20, 106.80, -6.21, 106.80);
        $this->assertEqualsWithDelta(1112.0, $d, 5.0);
    }

    public function test_jitter_offset_is_zero_for_nonpositive_meters(): void
    {
        [$dLat, $dLng] = PathInterpolator::jitterOffsetDegrees(-6.20, 0.0);
        $this->assertSame(0.0, $dLat);
        $this->assertSame(0.0, $dLng);

        [$dLat2, $dLng2] = PathInterpolator::jitterOffsetDegrees(-6.20, -5.0);
        $this->assertSame(0.0, $dLat2);
        $this->assertSame(0.0, $dLng2);
    }

    public function test_jitter_offset_scales_with_meters(): void
    {
        [$dLat, $dLng] = PathInterpolator::jitterOffsetDegrees(-6.20, 111.32);
        // 111.32 m / 111_320 m per degree = ~0.001 degrees latitude.
        $this->assertEqualsWithDelta(0.001, $dLat, 1e-4);
        // Longitude is a similar magnitude near the equator.
        $this->assertEqualsWithDelta(0.001, $dLng, 1e-3);
    }
}
