<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Tracking;

use App\Domain\Delivery\DeliveryStatus;
use App\Domain\Tracking\TrackingAuthenticator;
use App\Models\Customer;
use App\Models\Delivery;
use App\Models\Kitchen;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Unit-level coverage for {@see TrackingAuthenticator}.
 *
 * These tests focus on normalization, format validation, and the
 * uniform-failure contract (all negative paths return null, never
 * throw, never leak which factor was wrong).
 */
class TrackingAuthenticatorTest extends TestCase
{
    use RefreshDatabase;

    private function authenticator(): TrackingAuthenticator
    {
        return new TrackingAuthenticator();
    }

    /**
     * Build a scheduled delivery whose customer phone snapshot ends in
     * the given four digits.
     */
    private function scheduledWithPhoneEndingIn(string $lastFour): Delivery
    {
        return Delivery::factory()
            ->for(Kitchen::factory(), 'kitchen')
            ->for(Customer::factory(), 'customer')
            ->scheduled()
            ->create([
                'customer_phone' => '+62812345'.$lastFour,
            ]);
    }

    public function test_returns_delivery_for_exact_receipt_and_last_four(): void
    {
        $delivery = $this->scheduledWithPhoneEndingIn('4321');

        $found = $this->authenticator()->attempt(
            $delivery->receipt_number,
            '4321',
        );

        $this->assertNotNull($found);
        $this->assertSame($delivery->id, $found->id);
    }

    public function test_receipt_is_case_insensitive(): void
    {
        $delivery = $this->scheduledWithPhoneEndingIn('4321');

        $found = $this->authenticator()->attempt(
            strtolower($delivery->receipt_number),
            '4321',
        );

        $this->assertNotNull($found);
        $this->assertSame($delivery->id, $found->id);
    }

    public function test_receipt_normalization_accepts_stripped_form(): void
    {
        $delivery = $this->scheduledWithPhoneEndingIn('4321');

        // Remove hyphens, mix case, add extra spacing.
        $stripped = '  '.strtolower(str_replace('-', '', $delivery->receipt_number)).'  ';

        $found = $this->authenticator()->attempt($stripped, '4321');

        $this->assertNotNull($found);
        $this->assertSame($delivery->id, $found->id);
    }

    public function test_receipt_normalization_strips_arbitrary_separators(): void
    {
        $delivery = $this->scheduledWithPhoneEndingIn('4321');

        // Insert spaces and slashes into the receipt; normalization
        // must reduce it back to the canonical form.
        $noisy = str_replace('-', ' / ', $delivery->receipt_number);

        $found = $this->authenticator()->attempt($noisy, '4321');

        $this->assertNotNull($found);
        $this->assertSame($delivery->id, $found->id);
    }

    public function test_wrong_last_four_returns_null(): void
    {
        $delivery = $this->scheduledWithPhoneEndingIn('4321');

        $this->assertNull(
            $this->authenticator()->attempt($delivery->receipt_number, '9999'),
        );
    }

    public function test_unknown_receipt_returns_null(): void
    {
        $this->scheduledWithPhoneEndingIn('4321');

        $this->assertNull(
            $this->authenticator()->attempt('DEL-20260101-ZZZZ', '4321'),
        );
    }

    public function test_malformed_receipt_returns_null(): void
    {
        $auth = $this->authenticator();

        $this->assertNull($auth->attempt('', '4321'));
        $this->assertNull($auth->attempt('not-a-receipt', '4321'));
        $this->assertNull($auth->attempt('DEL-2026-XYZ', '4321'));
        $this->assertNull($auth->attempt('DEL-20260101-!!!!', '4321'));
    }

    public function test_malformed_last_four_returns_null(): void
    {
        $delivery = $this->scheduledWithPhoneEndingIn('4321');
        $auth = $this->authenticator();

        $this->assertNull($auth->attempt($delivery->receipt_number, ''));
        $this->assertNull($auth->attempt($delivery->receipt_number, '432'));
        $this->assertNull($auth->attempt($delivery->receipt_number, '43210'));
        $this->assertNull($auth->attempt($delivery->receipt_number, '43aa'));
        $this->assertNull($auth->attempt($delivery->receipt_number, '   '));
    }

    public function test_draft_delivery_is_not_authenticatable(): void
    {
        // Drafts have no receipt in the normal flow. Simulate a caller
        // who somehow knows a would-be receipt for a draft row.
        $draft = Delivery::factory()
            ->for(Kitchen::factory(), 'kitchen')
            ->for(Customer::factory(), 'customer')
            ->create([
                'status' => DeliveryStatus::Draft->value,
                'receipt_number' => 'DEL-20260101-AAAA',
                'customer_phone' => '+628123454321',
            ]);

        $this->assertNull(
            $this->authenticator()->attempt($draft->receipt_number, '4321'),
        );
    }

    public function test_trackable_across_all_non_draft_statuses(): void
    {
        $lastFour = '4321';

        $scheduled = $this->scheduledWithPhoneEndingIn($lastFour);
        $inTransit = Delivery::factory()->inTransit()->create([
            'customer_phone' => '+62812345'.$lastFour,
        ]);
        $delivered = Delivery::factory()->delivered()->create([
            'customer_phone' => '+62812345'.$lastFour,
        ]);
        $cancelled = Delivery::factory()->cancelledFromScheduled()->create([
            'customer_phone' => '+62812345'.$lastFour,
        ]);

        $auth = $this->authenticator();

        $this->assertSame($scheduled->id, $auth->attempt($scheduled->receipt_number, $lastFour)?->id);
        $this->assertSame($inTransit->id, $auth->attempt($inTransit->receipt_number, $lastFour)?->id);
        $this->assertSame($delivered->id, $auth->attempt($delivered->receipt_number, $lastFour)?->id);
        $this->assertSame($cancelled->id, $auth->attempt($cancelled->receipt_number, $lastFour)?->id);
    }

    public function test_missing_snapshot_phone_returns_null(): void
    {
        // Force a scheduled delivery with a blank phone snapshot; the
        // authenticator must refuse rather than throw.
        $delivery = Delivery::factory()
            ->for(Kitchen::factory(), 'kitchen')
            ->for(Customer::factory(), 'customer')
            ->scheduled()
            ->create([
                'customer_phone' => '',
            ]);

        $this->assertNull(
            $this->authenticator()->attempt($delivery->receipt_number, '4321'),
        );
    }
}
