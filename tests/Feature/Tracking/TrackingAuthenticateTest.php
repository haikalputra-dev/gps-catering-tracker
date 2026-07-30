<?php

declare(strict_types=1);

namespace Tests\Feature\Tracking;

use App\Http\Controllers\TrackingController;
use App\Http\Requests\Tracking\TrackingAuthenticateRequest;
use App\Models\Customer;
use App\Models\Delivery;
use App\Models\Kitchen;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * POST /track: receipt + phone-last-4 authentication.
 *
 * Covers:
 *  - success sets the session key and redirects to the status page
 *  - session id is regenerated on success (fixation defense, AR-42 revised)
 *  - every failure mode redirects back with the SAME generic error
 *  - draft deliveries cannot be authenticated
 *  - receipt is echoed back but phone digits are never repopulated
 */
class TrackingAuthenticateTest extends TestCase
{
    use RefreshDatabase;

    private function scheduledDelivery(string $lastFour = '4321'): Delivery
    {
        return Delivery::factory()
            ->for(Kitchen::factory(), 'kitchen')
            ->for(Customer::factory(), 'customer')
            ->scheduled()
            ->create([
                'customer_phone' => '+62812345'.$lastFour,
            ]);
    }

    public function test_valid_credentials_authenticate_and_redirect_to_status(): void
    {
        $delivery = $this->scheduledDelivery('4321');

        $response = $this->post(route('tracking.authenticate'), [
            'receipt_number' => $delivery->receipt_number,
            'phone_last_four' => '4321',
        ]);

        $response->assertRedirect(route('tracking.status'));
        $this->assertSame(
            $delivery->id,
            session(TrackingController::SESSION_KEY),
        );
    }

    public function test_session_is_regenerated_on_success(): void
    {
        $delivery = $this->scheduledDelivery('4321');

        // Prime the session so we can observe the id changing.
        $this->startSession();
        $originalId = session()->getId();

        $this->post(route('tracking.authenticate'), [
            'receipt_number' => $delivery->receipt_number,
            'phone_last_four' => '4321',
        ]);

        $this->assertNotSame($originalId, session()->getId());
    }

    public function test_wrong_last_four_shows_generic_error(): void
    {
        $delivery = $this->scheduledDelivery('4321');

        $response = $this->post(route('tracking.authenticate'), [
            'receipt_number' => $delivery->receipt_number,
            'phone_last_four' => '9999',
        ]);

        $response->assertRedirect(route('tracking.form'));
        $response->assertSessionHasErrors(['form' => TrackingAuthenticateRequest::GENERIC_ERROR]);
        $this->assertNull(session(TrackingController::SESSION_KEY));
    }

    public function test_unknown_receipt_shows_generic_error(): void
    {
        $this->scheduledDelivery('4321');

        $response = $this->post(route('tracking.authenticate'), [
            'receipt_number' => 'DEL-20260101-ZZZZ',
            'phone_last_four' => '4321',
        ]);

        $response->assertRedirect(route('tracking.form'));
        $response->assertSessionHasErrors(['form' => TrackingAuthenticateRequest::GENERIC_ERROR]);
        $this->assertNull(session(TrackingController::SESSION_KEY));
    }

    public function test_malformed_receipt_shows_same_generic_error(): void
    {
        $response = $this->post(route('tracking.authenticate'), [
            'receipt_number' => 'not-a-receipt',
            'phone_last_four' => '4321',
        ]);

        $response->assertRedirect(route('tracking.form'));
        $response->assertSessionHasErrors(['form' => TrackingAuthenticateRequest::GENERIC_ERROR]);
    }

    public function test_malformed_phone_shows_same_generic_error(): void
    {
        $delivery = $this->scheduledDelivery('4321');

        $response = $this->post(route('tracking.authenticate'), [
            'receipt_number' => $delivery->receipt_number,
            'phone_last_four' => 'abcd',
        ]);

        $response->assertRedirect(route('tracking.form'));
        $response->assertSessionHasErrors(['form' => TrackingAuthenticateRequest::GENERIC_ERROR]);
    }

    public function test_missing_fields_show_same_generic_error(): void
    {
        $response = $this->post(route('tracking.authenticate'), []);

        $response->assertRedirect(route('tracking.form'));
        $response->assertSessionHasErrors(['form' => TrackingAuthenticateRequest::GENERIC_ERROR]);
    }

    public function test_all_failure_modes_use_identical_error_text(): void
    {
        // Same-shaped error copy prevents information leaks about
        // which factor was wrong.
        $delivery = $this->scheduledDelivery('4321');

        $cases = [
            ['receipt_number' => 'DEL-20260101-ZZZZ', 'phone_last_four' => '4321'],
            ['receipt_number' => $delivery->receipt_number, 'phone_last_four' => '9999'],
            ['receipt_number' => 'garbage', 'phone_last_four' => '4321'],
            ['receipt_number' => $delivery->receipt_number, 'phone_last_four' => 'abcd'],
            ['receipt_number' => '', 'phone_last_four' => ''],
        ];

        foreach ($cases as $payload) {
            $response = $this->post(route('tracking.authenticate'), $payload);
            $response->assertSessionHasErrors(['form' => TrackingAuthenticateRequest::GENERIC_ERROR]);
        }
    }

    public function test_draft_delivery_cannot_authenticate(): void
    {
        $draft = Delivery::factory()
            ->for(Kitchen::factory(), 'kitchen')
            ->for(Customer::factory(), 'customer')
            ->create([
                'status' => \App\Domain\Delivery\DeliveryStatus::Draft->value,
                'receipt_number' => 'DEL-20260101-AAAA',
                'customer_phone' => '+628123454321',
            ]);

        $response = $this->post(route('tracking.authenticate'), [
            'receipt_number' => $draft->receipt_number,
            'phone_last_four' => '4321',
        ]);

        $response->assertRedirect(route('tracking.form'));
        $response->assertSessionHasErrors(['form' => TrackingAuthenticateRequest::GENERIC_ERROR]);
        $this->assertNull(session(TrackingController::SESSION_KEY));
    }

    public function test_receipt_is_echoed_back_but_phone_is_not(): void
    {
        $response = $this->post(route('tracking.authenticate'), [
            'receipt_number' => 'DEL-20260101-ZZZZ',
            'phone_last_four' => '4321',
        ]);

        $response->assertRedirect(route('tracking.form'));
        $this->assertSame('DEL-20260101-ZZZZ', old('receipt_number'));
        $this->assertNull(old('phone_last_four'));
    }

    public function test_receipt_and_phone_are_trimmed_and_uppercased_on_authentication(): void
    {
        $delivery = $this->scheduledDelivery('4321');

        $response = $this->post(route('tracking.authenticate'), [
            'receipt_number' => '  '.strtolower($delivery->receipt_number).'  ',
            'phone_last_four' => ' 4321 ',
        ]);

        $response->assertRedirect(route('tracking.status'));
        $this->assertSame($delivery->id, session(TrackingController::SESSION_KEY));
    }
}
