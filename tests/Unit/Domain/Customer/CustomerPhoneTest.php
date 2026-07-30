<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Customer;

use App\Domain\Customer\CustomerPhone;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class CustomerPhoneTest extends TestCase
{
    #[Test]
    public function it_strips_separators_and_preserves_leading_plus(): void
    {
        $this->assertSame('+628123456789', CustomerPhone::normalize('+62 812-3456-789'));
        $this->assertSame('+628123456789', CustomerPhone::normalize(' +62 (812) 3456 789 '));
    }

    #[Test]
    public function it_normalizes_local_indonesian_format(): void
    {
        $this->assertSame('08123456789', CustomerPhone::normalize('0812-3456-789'));
    }

    #[Test]
    public function it_returns_empty_string_for_non_string_input(): void
    {
        $this->assertSame('', CustomerPhone::normalize(null));
        $this->assertSame('', CustomerPhone::normalize(12345));
        $this->assertSame('', CustomerPhone::normalize(['+628123']));
    }

    #[Test]
    public function it_returns_empty_string_for_blank_input(): void
    {
        $this->assertSame('', CustomerPhone::normalize(''));
        $this->assertSame('', CustomerPhone::normalize('   '));
    }

    /**
     * @return array<string, array{string, bool}>
     */
    public static function validityProvider(): array
    {
        return [
            'valid international' => ['+628123456789', true],
            'valid local' => ['08123456789', true],
            'valid with separators' => ['+62 812-3456-789', true],
            'minimum length nine digits' => ['081234567', true],
            'maximum length fifteen digits' => ['+123456789012345', true],
            'too short' => ['+6281234', false],
            'too long' => ['+1234567890123456', false],
            'contains letters' => ['+62812ABC6789', false],
            'empty' => ['', false],
            'only separators' => ['() - ', false],
            'plus in the middle raw' => ['081+23456789', true],
        ];
    }

    #[Test]
    #[DataProvider('validityProvider')]
    public function it_validates_expected_forms(string $raw, bool $valid): void
    {
        $this->assertSame($valid, CustomerPhone::isValid($raw));
    }

    #[Test]
    public function from_input_returns_normalized_value_on_success(): void
    {
        $this->assertSame('+628123456789', CustomerPhone::fromInput('+62 812-3456-789'));
    }

    #[Test]
    public function from_input_throws_on_invalid_value(): void
    {
        $this->expectException(InvalidArgumentException::class);
        CustomerPhone::fromInput('abc');
    }

    #[Test]
    public function constants_expose_length_bounds(): void
    {
        $this->assertSame(9, CustomerPhone::MIN_DIGITS);
        $this->assertSame(15, CustomerPhone::MAX_DIGITS);
    }
}
