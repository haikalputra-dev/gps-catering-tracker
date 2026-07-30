<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Delivery;

use App\Domain\Delivery\PricingCalculator;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Unit tests for the delivery fee calculator (AR-04, AR-29 revised).
 *
 * Runtime config overrides via config()->set(...) exercise the
 * three-key configuration surface documented in config/pricing.php.
 */
class PricingCalculatorTest extends TestCase
{
    private PricingCalculator $calc;

    protected function setUp(): void
    {
        parent::setUp();

        // Force default constants so no local .env leakage affects
        // truth-table assertions.
        config()->set('pricing.minimum_fee_rupiah', 5000);
        config()->set('pricing.rate_per_km_rupiah', 2000);
        config()->set('pricing.fee_rounding_step_rupiah', 100);

        $this->calc = new PricingCalculator();
    }

    /**
     * @return array<string, array{0: float, 1: int}>
     */
    public static function truthTable(): array
    {
        return [
            '0.000 km -> floor'                 => [0.000, 5000],
            '0.500 km -> floor (raw 1000)'      => [0.500, 5000],
            '2.500 km -> floor (raw 5000)'      => [2.500, 5000],
            '3.000 km -> 6000 (raw 6000)'       => [3.000, 6000],
            '3.567 km -> 7100 (raw 7134)'       => [3.567, 7100],
            '3.599 km -> 7200 (raw 7198)'       => [3.599, 7200],
            '5.025 km -> 10100 (raw 10050)'     => [5.025, 10100],
            '5.075 km -> 10200 (raw 10150)'     => [5.075, 10200],
            '100.000 km -> 200000 (raw 200000)' => [100.000, 200000],
        ];
    }

    #[Test]
    #[DataProvider('truthTable')]
    public function it_matches_the_truth_table(float $distanceKm, int $expectedFee): void
    {
        $this->assertSame($expectedFee, $this->calc->feeForDistanceKm($distanceKm));
    }

    #[Test]
    public function it_rejects_negative_distance(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->calc->feeForDistanceKm(-0.001);
    }

    #[Test]
    public function minimum_fee_override_is_honored(): void
    {
        config()->set('pricing.minimum_fee_rupiah', 8000);
        $this->assertSame(8000, $this->calc->feeForDistanceKm(1.0));
    }

    #[Test]
    public function rate_override_is_honored(): void
    {
        config()->set('pricing.rate_per_km_rupiah', 3000);
        // 3 km * 3000 = 9000, above the 5000 floor.
        $this->assertSame(9000, $this->calc->feeForDistanceKm(3.0));
    }

    #[Test]
    public function rounding_step_override_is_honored(): void
    {
        config()->set('pricing.fee_rounding_step_rupiah', 500);
        // 3.567 km * 2000 = 7134; nearest 500 = 7000; floor 5000 -> 7000.
        $this->assertSame(7000, $this->calc->feeForDistanceKm(3.567));
    }

    #[Test]
    public function non_positive_rounding_step_throws(): void
    {
        config()->set('pricing.fee_rounding_step_rupiah', 0);
        $this->expectException(InvalidArgumentException::class);
        $this->calc->feeForDistanceKm(1.0);
    }

    #[Test]
    public function return_type_is_int(): void
    {
        $value = $this->calc->feeForDistanceKm(3.0);
        $this->assertIsInt($value);
    }

    #[Test]
    public function zero_distance_returns_the_floor(): void
    {
        $this->assertSame(5000, $this->calc->feeForDistanceKm(0.0));
    }
}
