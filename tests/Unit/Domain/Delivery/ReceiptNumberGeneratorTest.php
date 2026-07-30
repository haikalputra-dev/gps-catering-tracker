<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Delivery;

use App\Domain\Delivery\ReceiptNumberGenerator;
use App\Models\Customer;
use App\Models\Delivery;
use App\Models\Kitchen;
use App\Models\User;
use DateTimeImmutable;
use DateTimeZone;
use Illuminate\Foundation\Testing\RefreshDatabase;
use RuntimeException;
use Tests\TestCase;

class ReceiptNumberGeneratorTest extends TestCase
{
    use RefreshDatabase;

    public function test_receipt_number_matches_configured_format(): void
    {
        $scheduledAt = new DateTimeImmutable('2026-08-15T02:00:00', new DateTimeZone('UTC'));

        $generator = new ReceiptNumberGenerator();
        $receipt = $generator->generate($scheduledAt);

        // 2026-08-15 02:00 UTC == 2026-08-15 09:00 Asia/Jakarta.
        $this->assertMatchesRegularExpression(
            '/^DEL-20260815-[ABCDEFGHJKMNPQRSTUVWXYZ23456789]{4}$/',
            $receipt,
        );
    }

    public function test_date_segment_uses_jakarta_timezone(): void
    {
        // 2026-08-15 22:00 UTC is 2026-08-16 05:00 Asia/Jakarta.
        $scheduledAt = new DateTimeImmutable('2026-08-15T22:00:00', new DateTimeZone('UTC'));

        $receipt = (new ReceiptNumberGenerator())->generate($scheduledAt);

        $this->assertStringStartsWith('DEL-20260816-', $receipt);
    }

    public function test_alphabet_excludes_ambiguous_glyphs(): void
    {
        $scheduledAt = new DateTimeImmutable('now', new DateTimeZone('UTC'));

        for ($i = 0; $i < 50; $i++) {
            $receipt = (new ReceiptNumberGenerator())->generate($scheduledAt);
            $suffix = substr($receipt, -4);
            $this->assertDoesNotMatchRegularExpression('/[0O1IL]/', $suffix, "Suffix {$suffix} contains ambiguous glyph");
        }
    }

    public function test_generator_retries_on_collision_then_succeeds(): void
    {
        config()->set('delivery.receipt_random_length', 1);
        config()->set('delivery.receipt_random_alphabet', 'AB');

        $scheduledAt = new DateTimeImmutable('2026-08-15T02:00:00', new DateTimeZone('UTC'));
        $datePart = '20260815';

        // Occupy one of the two possible receipts; the generator must find the other.
        $user = User::factory()->owner()->create();
        Delivery::factory()
            ->for(Kitchen::factory(), 'kitchen')
            ->for(Customer::factory(), 'customer')
            ->create([
                'receipt_number' => "DEL-{$datePart}-A",
                'created_by_user_id' => $user->id,
            ]);

        $receipt = (new ReceiptNumberGenerator())->generate($scheduledAt);

        $this->assertSame("DEL-{$datePart}-B", $receipt);
    }

    public function test_generator_throws_when_all_receipts_collide(): void
    {
        config()->set('delivery.receipt_random_length', 1);
        config()->set('delivery.receipt_random_alphabet', 'A');

        $scheduledAt = new DateTimeImmutable('2026-08-15T02:00:00', new DateTimeZone('UTC'));
        $datePart = '20260815';

        // Only one possible receipt exists; occupy it.
        $user = User::factory()->owner()->create();
        Delivery::factory()
            ->for(Kitchen::factory(), 'kitchen')
            ->for(Customer::factory(), 'customer')
            ->create([
                'receipt_number' => "DEL-{$datePart}-A",
                'created_by_user_id' => $user->id,
            ]);

        $this->expectException(RuntimeException::class);
        (new ReceiptNumberGenerator())->generate($scheduledAt);
    }

    public function test_generator_throws_on_empty_prefix(): void
    {
        config()->set('delivery.receipt_prefix', '');
        $this->expectException(RuntimeException::class);
        (new ReceiptNumberGenerator())->generate(new DateTimeImmutable('now', new DateTimeZone('UTC')));
    }

    public function test_generator_throws_on_zero_length(): void
    {
        config()->set('delivery.receipt_random_length', 0);
        $this->expectException(RuntimeException::class);
        (new ReceiptNumberGenerator())->generate(new DateTimeImmutable('now', new DateTimeZone('UTC')));
    }

    public function test_generator_throws_on_empty_alphabet(): void
    {
        config()->set('delivery.receipt_random_alphabet', '');
        $this->expectException(RuntimeException::class);
        (new ReceiptNumberGenerator())->generate(new DateTimeImmutable('now', new DateTimeZone('UTC')));
    }
}
