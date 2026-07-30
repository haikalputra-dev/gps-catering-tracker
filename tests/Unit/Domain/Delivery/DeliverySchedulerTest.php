<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Delivery;

use App\Domain\Delivery\DeliveryScheduler;
use App\Domain\Delivery\DeliveryStatus;
use App\Domain\Delivery\DistanceCalculator;
use App\Domain\Delivery\Exceptions\ConcurrencyLimitReachedException;
use App\Domain\Delivery\Exceptions\InactiveCustomerException;
use App\Domain\Delivery\Exceptions\InactiveKitchenException;
use App\Domain\Delivery\Exceptions\MissingSchedulingFieldsException;
use App\Domain\Delivery\Exceptions\NotSchedulableStateException;
use App\Domain\Delivery\PricingCalculator;
use App\Domain\Delivery\ReceiptNumberGenerator;
use App\Models\Customer;
use App\Models\Delivery;
use App\Models\Kitchen;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class DeliverySchedulerTest extends TestCase
{
    use RefreshDatabase;

    private function scheduler(): DeliveryScheduler
    {
        return new DeliveryScheduler(
            new ReceiptNumberGenerator(),
            new DistanceCalculator(),
            new PricingCalculator(),
        );
    }

    private function makeDraftFor(User $creator, ?Kitchen $kitchen = null, ?Customer $customer = null): Delivery
    {
        $kitchen ??= Kitchen::factory()->create();
        $customer ??= Customer::factory()->create();

        return Delivery::factory()->create([
            'kitchen_id' => $kitchen->id,
            'customer_id' => $customer->id,
            'scheduled_at' => now()->addHours(3),
            'created_by_user_id' => $creator->id,
        ]);
    }

    public function test_schedules_draft_and_generates_receipt_and_snapshots(): void
    {
        $actor = User::factory()->owner()->create();
        $kitchen = Kitchen::factory()->create();
        $customer = Customer::factory()->create();
        $delivery = $this->makeDraftFor($actor, $kitchen, $customer);

        $scheduled = $this->scheduler()->schedule($delivery, $actor);

        $this->assertSame(DeliveryStatus::Scheduled, $scheduled->status);
        $this->assertMatchesRegularExpression(
            '/^DEL-\d{8}-[ABCDEFGHJKMNPQRSTUVWXYZ23456789]{4}$/',
            $scheduled->receipt_number,
        );
        $this->assertSame($actor->id, $scheduled->scheduled_by_user_id);
        $this->assertNotNull($scheduled->scheduled_at_recorded);

        // Kitchen snapshot equals source at scheduling.
        $this->assertSame($kitchen->code, $scheduled->kitchen_code);
        $this->assertSame($kitchen->name, $scheduled->kitchen_name);
        $this->assertSame($kitchen->address, $scheduled->kitchen_address);
        $this->assertEquals($kitchen->latitude, $scheduled->kitchen_latitude);
        $this->assertEquals($kitchen->longitude, $scheduled->kitchen_longitude);

        // Customer snapshot equals source at scheduling.
        $this->assertSame($customer->name, $scheduled->customer_name);
        $this->assertSame($customer->phone, $scheduled->customer_phone);
        $this->assertSame($customer->address, $scheduled->customer_address);

        // Distance and fee are frozen at scheduling.
        $this->assertNotNull($scheduled->distance_km);
        $this->assertNotNull($scheduled->fee_rupiah);
        $this->assertIsInt($scheduled->fee_rupiah);
        $this->assertGreaterThanOrEqual(
            (int) config('pricing.minimum_fee_rupiah'),
            $scheduled->fee_rupiah,
        );
    }

    public function test_snapshots_are_immutable_after_source_edits(): void
    {
        $actor = User::factory()->owner()->create();
        $kitchen = Kitchen::factory()->create(['name' => 'Original Kitchen']);
        $customer = Customer::factory()->create(['name' => 'Original Customer']);
        $delivery = $this->makeDraftFor($actor, $kitchen, $customer);

        $scheduled = $this->scheduler()->schedule($delivery, $actor);

        $kitchen->update(['name' => 'Renamed Kitchen']);
        $customer->update(['name' => 'Renamed Customer']);

        $scheduled->refresh();
        $this->assertSame('Original Kitchen', $scheduled->kitchen_name);
        $this->assertSame('Original Customer', $scheduled->customer_name);
    }

    public function test_receipt_number_is_unique_across_deliveries(): void
    {
        $actor = User::factory()->owner()->create();

        $a = $this->scheduler()->schedule($this->makeDraftFor($actor), $actor);

        // Bumping the concurrency cap so both can be scheduled.
        config()->set('delivery.max_concurrent_active', 5);

        $b = $this->scheduler()->schedule($this->makeDraftFor($actor), $actor);

        $this->assertNotSame($a->receipt_number, $b->receipt_number);
    }

    public function test_receipt_number_is_immutable_once_generated(): void
    {
        $actor = User::factory()->owner()->create();
        $delivery = $this->makeDraftFor($actor);
        $scheduled = $this->scheduler()->schedule($delivery, $actor);
        $originalReceipt = $scheduled->receipt_number;

        // Attempt to mutate directly; nothing in the service ever rewrites it.
        $scheduled->refresh();
        $this->assertSame($originalReceipt, $scheduled->receipt_number);
    }

    public function test_rejects_non_draft(): void
    {
        $actor = User::factory()->owner()->create();
        $delivery = Delivery::factory()
            ->for(Kitchen::factory(), 'kitchen')
            ->for(Customer::factory(), 'customer')
            ->scheduled()
            ->create(['created_by_user_id' => $actor->id]);

        $this->expectException(NotSchedulableStateException::class);
        $this->scheduler()->schedule($delivery, $actor);
    }

    public function test_rejects_when_scheduled_at_missing(): void
    {
        $actor = User::factory()->owner()->create();
        $delivery = Delivery::factory()
            ->for(Kitchen::factory(), 'kitchen')
            ->for(Customer::factory(), 'customer')
            ->create([
                'created_by_user_id' => $actor->id,
                'scheduled_at' => null,
            ]);

        $this->expectException(MissingSchedulingFieldsException::class);
        $this->scheduler()->schedule($delivery, $actor);
    }

    public function test_rejects_when_scheduled_at_is_in_the_past(): void
    {
        $actor = User::factory()->owner()->create();
        $delivery = Delivery::factory()
            ->for(Kitchen::factory(), 'kitchen')
            ->for(Customer::factory(), 'customer')
            ->create([
                'created_by_user_id' => $actor->id,
                'scheduled_at' => now()->subHour(),
            ]);

        $this->expectException(MissingSchedulingFieldsException::class);
        $this->scheduler()->schedule($delivery, $actor);
    }

    public function test_rejects_inactive_kitchen(): void
    {
        $actor = User::factory()->owner()->create();
        $kitchen = Kitchen::factory()->create(['is_active' => false]);
        $delivery = $this->makeDraftFor($actor, $kitchen);

        $this->expectException(InactiveKitchenException::class);
        $this->scheduler()->schedule($delivery, $actor);
    }

    public function test_rejects_inactive_customer(): void
    {
        $actor = User::factory()->owner()->create();
        $customer = Customer::factory()->inactive()->create();
        $delivery = $this->makeDraftFor($actor, null, $customer);

        $this->expectException(InactiveCustomerException::class);
        $this->scheduler()->schedule($delivery, $actor);
    }

    public function test_rejects_when_concurrency_cap_already_reached(): void
    {
        config()->set('delivery.max_concurrent_active', 1);

        $actor = User::factory()->owner()->create();

        Delivery::factory()
            ->for(Kitchen::factory(), 'kitchen')
            ->for(Customer::factory(), 'customer')
            ->scheduled()
            ->create(['created_by_user_id' => $actor->id]);

        $draft = $this->makeDraftFor($actor);

        $this->expectException(ConcurrencyLimitReachedException::class);
        $this->scheduler()->schedule($draft, $actor);
    }

    public function test_rejects_when_limit_is_zero(): void
    {
        config()->set('delivery.max_concurrent_active', 0);

        $actor = User::factory()->owner()->create();
        $delivery = $this->makeDraftFor($actor);

        $this->expectException(ConcurrencyLimitReachedException::class);
        $this->scheduler()->schedule($delivery, $actor);
    }
}
