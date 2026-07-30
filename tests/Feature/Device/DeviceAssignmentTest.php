<?php

declare(strict_types=1);

namespace Tests\Feature\Device;

use App\Models\Device;
use App\Models\DeviceAssignment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DeviceAssignmentTest extends TestCase
{
    use RefreshDatabase;

    private function actingAsOwner(): User
    {
        $owner = User::factory()->owner()->create();
        $this->actingAs($owner);

        return $owner;
    }

    public function test_owner_can_assign_courier_to_device(): void
    {
        $this->actingAsOwner();
        $device = Device::factory()->create();
        $courier = User::factory()->courier()->create();

        $response = $this->post(route('devices.assign', $device), [
            'courier_id' => $courier->id,
            'notes' => 'primary handset',
        ]);

        $response->assertRedirect(route('devices.show', $device));
        $this->assertSame(1, DeviceAssignment::query()->count());
        $current = $device->fresh()->currentAssignment;
        $this->assertNotNull($current);
        $this->assertSame($courier->id, $current->courier_id);
        $this->assertNull($current->unassigned_at);
    }

    public function test_owner_can_unassign_current_courier(): void
    {
        $this->actingAsOwner();
        $device = Device::factory()->create();
        $courier = User::factory()->courier()->create();
        $this->post(route('devices.assign', $device), ['courier_id' => $courier->id]);

        $response = $this->post(route('devices.unassign', $device));

        $response->assertRedirect(route('devices.show', $device));
        $this->assertNull($device->fresh()->currentAssignment);
        $this->assertSame(1, DeviceAssignment::query()->count(), 'history row preserved');
        $this->assertNotNull(DeviceAssignment::query()->first()->unassigned_at);
    }

    public function test_reassigning_courier_closes_previous_binding(): void
    {
        $this->actingAsOwner();
        $device = Device::factory()->create();
        $courierA = User::factory()->courier()->create();
        $courierB = User::factory()->courier()->create();

        $this->post(route('devices.assign', $device), ['courier_id' => $courierA->id]);
        $this->post(route('devices.assign', $device), ['courier_id' => $courierB->id]);

        $device->refresh();
        $this->assertSame($courierB->id, $device->currentAssignment->courier_id);
        $this->assertSame(2, DeviceAssignment::query()->count());
        $this->assertSame(1, DeviceAssignment::query()->whereNull('unassigned_at')->count());
    }

    public function test_cannot_bind_a_courier_already_bound_to_a_different_device(): void
    {
        $this->actingAsOwner();
        $deviceOne = Device::factory()->create();
        $deviceTwo = Device::factory()->create();
        $courier = User::factory()->courier()->create();

        $this->post(route('devices.assign', $deviceOne), ['courier_id' => $courier->id]);

        $response = $this->post(route('devices.assign', $deviceTwo), [
            'courier_id' => $courier->id,
        ]);

        $response->assertSessionHasErrors('courier_id');
        $this->assertNull($deviceTwo->fresh()->currentAssignment);
    }

    public function test_cannot_assign_inactive_courier(): void
    {
        $this->actingAsOwner();
        $device = Device::factory()->create();
        $inactive = User::factory()->courier()->inactive()->create();

        $response = $this->post(route('devices.assign', $device), [
            'courier_id' => $inactive->id,
        ]);

        $response->assertSessionHasErrors('courier_id');
        $this->assertNull($device->fresh()->currentAssignment);
    }

    public function test_cannot_assign_staff_or_owner_as_courier(): void
    {
        $this->actingAsOwner();
        $device = Device::factory()->create();
        $staff = User::factory()->staff()->create();

        $response = $this->post(route('devices.assign', $device), [
            'courier_id' => $staff->id,
        ]);

        $response->assertSessionHasErrors('courier_id');
    }

    public function test_show_page_lists_assignment_history_in_reverse_chronological_order(): void
    {
        $this->actingAsOwner();
        $device = Device::factory()->create();
        $courierA = User::factory()->courier()->create(['name' => 'Alpha Courier']);
        $courierB = User::factory()->courier()->create(['name' => 'Bravo Courier']);

        $this->post(route('devices.assign', $device), ['courier_id' => $courierA->id]);
        $this->post(route('devices.assign', $device), ['courier_id' => $courierB->id]);

        $response = $this->get(route('devices.show', $device));
        $response->assertOk();

        $body = (string) $response->getContent();
        $bravoPos = strpos($body, 'Bravo Courier');
        $alphaPos = strpos($body, 'Alpha Courier');
        $this->assertNotFalse($bravoPos);
        $this->assertNotFalse($alphaPos);
        $this->assertLessThan(
            $alphaPos,
            $bravoPos,
            'newest binding (Bravo) should render above older (Alpha)',
        );
    }

    public function test_unassign_is_idempotent_when_no_current_binding(): void
    {
        $this->actingAsOwner();
        $device = Device::factory()->create();

        $response = $this->post(route('devices.unassign', $device));

        $response->assertRedirect(route('devices.show', $device));
        $this->assertSame(0, DeviceAssignment::query()->count());
    }

    public function test_staff_cannot_assign_or_unassign(): void
    {
        $this->actingAs(User::factory()->staff()->create());
        $device = Device::factory()->create();
        $courier = User::factory()->courier()->create();

        $this->post(route('devices.assign', $device), ['courier_id' => $courier->id])
            ->assertForbidden();
        $this->post(route('devices.unassign', $device))->assertForbidden();
    }
}
