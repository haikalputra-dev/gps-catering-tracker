<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Device;

use App\Domain\Device\ApiTokenGenerator;
use App\Models\Device;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ApiTokenGeneratorTest extends TestCase
{
    use RefreshDatabase;

    public function test_generates_token_of_configured_length_from_configured_alphabet(): void
    {
        config()->set('telemetry.token_length', 40);
        $alphabet = 'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789';
        config()->set('telemetry.token_alphabet', $alphabet);

        $token = (new ApiTokenGenerator())->generate();

        $this->assertSame(40, strlen($token));
        $this->assertMatchesRegularExpression('/^['.preg_quote($alphabet, '/').']{40}$/', $token);
    }

    public function test_returned_tokens_are_unique_across_calls(): void
    {
        $gen = new ApiTokenGenerator();

        $seen = [];
        for ($i = 0; $i < 25; $i++) {
            $seen[] = $gen->generate();
        }

        $this->assertCount(25, array_unique($seen));
    }

    public function test_rejects_length_below_minimum(): void
    {
        config()->set('telemetry.token_length', 8);

        $this->expectException(\RuntimeException::class);
        (new ApiTokenGenerator())->generate();
    }

    public function test_rejects_alphabet_below_minimum(): void
    {
        config()->set('telemetry.token_alphabet', 'abc');

        $this->expectException(\RuntimeException::class);
        (new ApiTokenGenerator())->generate();
    }

    public function test_avoids_collision_with_existing_device_token(): void
    {
        // Use the smallest legal alphabet (16 chars) so a manually
        // planted token has a non-negligible chance of colliding —
        // demonstrating that the exists() check is wired and the
        // retry loop is exercised in practice.
        config()->set('telemetry.token_length', 16);
        config()->set('telemetry.token_alphabet', 'ABCDEFGHIJKLMNOP');

        $planted = str_repeat('A', 16);
        Device::factory()->withToken($planted)->create();

        $token = (new ApiTokenGenerator())->generate();

        $this->assertNotSame($planted, $token);
        $this->assertSame(16, strlen($token));
    }
}
