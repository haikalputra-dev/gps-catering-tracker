<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Kitchen;

use App\Domain\Kitchen\KitchenCode;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

class KitchenCodeTest extends TestCase
{
    public function test_normalize_trims_and_uppercases(): void
    {
        $this->assertSame('KIT-01', KitchenCode::normalize('  kit-01 '));
        $this->assertSame('SUKABUMI-1', KitchenCode::normalize(' sukabumi-1'));
    }

    public function test_normalize_null_returns_empty_string(): void
    {
        $this->assertSame('', KitchenCode::normalize(null));
    }

    public function test_is_valid_accepts_uppercase_digits_and_hyphens(): void
    {
        $this->assertTrue(KitchenCode::isValid('KIT-01'));
        $this->assertTrue(KitchenCode::isValid('KITCHEN-001'));
        $this->assertTrue(KitchenCode::isValid('A1'));
    }

    public function test_is_valid_rejects_spaces_and_punctuation(): void
    {
        $this->assertFalse(KitchenCode::isValid('KIT 01'));
        $this->assertFalse(KitchenCode::isValid('KIT_01'));
        $this->assertFalse(KitchenCode::isValid('KIT.01'));
        $this->assertFalse(KitchenCode::isValid(''));
    }

    public function test_is_valid_enforces_max_length(): void
    {
        $this->assertTrue(KitchenCode::isValid(str_repeat('A', 30)));
        $this->assertFalse(KitchenCode::isValid(str_repeat('A', 31)));
    }

    public function test_from_input_throws_on_invalid(): void
    {
        $this->expectException(InvalidArgumentException::class);
        KitchenCode::fromInput('KIT 01');
    }

    public function test_from_input_returns_normalized_form(): void
    {
        $this->assertSame('KIT-01', KitchenCode::fromInput(' kit-01 '));
    }
}
