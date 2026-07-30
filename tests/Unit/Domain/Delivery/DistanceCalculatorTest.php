<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Delivery;

use App\Domain\Delivery\DistanceCalculator;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Unit tests for the Haversine distance calculator (AR-32).
 *
 * All assertions are self-contained; no database or HTTP is involved.
 */
class DistanceCalculatorTest extends TestCase
{
    private DistanceCalculator $calc;

    protected function setUp(): void
    {
        parent::setUp();
        $this->calc = new DistanceCalculator();
    }

    #[Test]
    public function it_returns_zero_for_identical_points(): void
    {
        $d = $this->calc->between(-6.1754, 106.8272, -6.1754, 106.8272);
        $this->assertEqualsWithDelta(0.0, $d, 1e-9);
    }

    #[Test]
    public function one_degree_of_longitude_at_the_equator_is_about_111_km(): void
    {
        $d = $this->calc->between(0.0, 0.0, 0.0, 1.0);
        $this->assertEqualsWithDelta(111.195, $d, 0.01);
    }

    #[Test]
    public function one_degree_of_latitude_is_about_111_km(): void
    {
        $d = $this->calc->between(0.0, 0.0, 1.0, 0.0);
        $this->assertEqualsWithDelta(111.195, $d, 0.01);
    }

    #[Test]
    public function it_is_symmetric(): void
    {
        $a = $this->calc->between(-6.1754, 106.8272, -6.5950, 106.7962);
        $b = $this->calc->between(-6.5950, 106.7962, -6.1754, 106.8272);
        $this->assertEqualsWithDelta($a, $b, 1e-9);
    }

    #[Test]
    public function jakarta_monas_to_bogor_is_in_expected_range(): void
    {
        // Reference pair: Jakarta Monas (-6.1754, 106.8272) to
        // Bogor city (-6.5950, 106.7962). Expected 46..48 km geodesic.
        $d = $this->calc->between(-6.1754, 106.8272, -6.5950, 106.7962);
        $this->assertGreaterThan(46.0, $d);
        $this->assertLessThan(48.0, $d);
    }

    #[Test]
    public function antipodal_points_return_half_circumference(): void
    {
        // Antipode of (0, 0) is (0, 180); distance is pi * R.
        $d = $this->calc->between(0.0, 0.0, 0.0, 180.0);
        $expected = M_PI * DistanceCalculator::EARTH_RADIUS_KM;
        $this->assertEqualsWithDelta($expected, $d, 1.0);
    }

    #[Test]
    public function result_is_finite_and_non_negative(): void
    {
        $d = $this->calc->between(-6.1754, 106.8272, 40.7128, -74.0060);
        $this->assertTrue(is_finite($d));
        $this->assertGreaterThanOrEqual(0.0, $d);
    }

    #[Test]
    public function latitude_below_minus_ninety_throws(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->calc->between(-90.0001, 0.0, 0.0, 0.0);
    }

    #[Test]
    public function latitude_above_ninety_throws(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->calc->between(0.0, 0.0, 90.0001, 0.0);
    }

    #[Test]
    public function longitude_below_minus_180_throws(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->calc->between(0.0, -180.0001, 0.0, 0.0);
    }

    #[Test]
    public function longitude_above_180_throws(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->calc->between(0.0, 0.0, 0.0, 180.0001);
    }

    #[Test]
    public function earth_radius_constant_is_the_iugg_mean(): void
    {
        $this->assertSame(6371.0088, DistanceCalculator::EARTH_RADIUS_KM);
    }
}
