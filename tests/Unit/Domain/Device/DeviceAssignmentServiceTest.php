<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Device;

use App\Domain\Device\DeviceAssignmentService;
use App\Domain\Device\Exceptions\CourierAlreadyBoundException;
use App\Domain\Device\Exceptions\InactiveCourierException;
use App\Domain\Device\Exceptions\InactiveDeviceException;
use App\Domain\Device\Exceptions\NotCourierRoleException;
use App\Models\Device;
use App\Models\DeviceAssignment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DeviceAssignmentServiceTest extends TestCase
{
    use RefreshDatabase;

    private function service(): DeviceAssignmentService
    {
        return app(DeviceAssignmentService::class);
    }

    public function test_assign_creates_open_row_with_actor(): void
    {
        $device = Device::factory()->create();
        $courier = User::factory()->courier()->create();
        $owner = User::factory()->owner()->create();

        $assignment = $this->service()->assign($device, $courier, $owner, 'kickoff');

        $this->assertNull($assignment->unassigned_at);
        $this->assertSame($device->id, $assignment->device_id);
        $this->assertSame($courier->id, $assignment->courier_id);
        $this->assertSame($owner->id, $assignment->assigned_by_user_id);
        $this->assertSame('kickoff', $assignment->notes);
        $this->assertNotNull($assignment->assigned_at);
    }

    public function test_assign_is_idempotent_when_courier_already_bound_to_same_device(): void
    {
        $device = Device::factory()->create();
        $courier = User::factory()->courier()->create();
        $owner = User::factory()->owner()->create();

        $first = $this->service()->assign($device, $courier, $owner);
        $second = $this->service()->assign($device, $courier, $owner);

        $this->assertSame($first->id, $second->id);
        $this->assertSame(1, DeviceAssignment::query()->count());
    }

    public function test_reassigning_device_closes_previous_assignment_and_opens_new(): void
    {
        $device = Device::factory()->create();
        $courierA = User::factory()->courier()->create(['name' => 'A']);
        $courierB = User::factory()->courier()->create(['name' => 'B']);
        $owner = User::factory()->owner()->create();

        $first = $this->service()->assign($device, $courierA, $owner);
        $second = $this->service()->assign($device, $courierB, $owner);

        $first->refresh();
        $this->assertNotNull($first->unassigned_at, 'previous assignment should be closed');
        $this->assertSame($owner->id, $first->unassigned_by_user_id);
        $this->assertNull($second->unassigned_at);
        $this->assertSame($courierB->id, $second->courier_id);
        $this->assertSame(2, DeviceAssignment::query()->count());
    }

    public function test_courier_already_bound_to_a_different_device_refuses(): void
    {
        $deviceOne = Device::factory()->create();
        $deviceTwo = Device::factory()->create();
        $courier = User::factory()->courier()->create();
        $owner = User::factory()->owner()->create();

        $this->service()->assign($deviceOne, $courier, $owner);

        $this->expectException(CourierAlreadyBoundException::class);
        $this->service()->assign($deviceTwo, $courier, $owner);
    }

    public function test_non_courier_target_is_refused(): void
    {
        $device = Device::factory()->create();
        $staff = User::factory()->staff()->create();
        $owner = User::factory()->owner()->create();

        $this->expectException(NotCourierRoleException::class);
        $this->service()->assign($device, $staff, $owner);
    }

    public function test_inactive_courier_is_refused(): void
    {
        $device = Device::factory()->create();
        $courier = User::factory()->courier()->inactive()->create();
        $owner = User::factory()->owner()->create();

        $this->expectException(InactiveCourierException::class);
        $this->service()->assign($device, $courier, $owner);
    }

    public function test_inactive_device_is_refused(): void
    {
        $device = Device::factory()->inactive()->create();
        $courier = User::factory()->courier()->create();
        $owner = User::factory()->owner()->create();

        $this->expectException(InactiveDeviceException::class);
        $this->service()->assign($device, $courier, $owner);
    }

    public function test_unassign_closes_open_row_with_actor(): void
    {
        $device = Device::factory()->create();
        $courier = User::factory()->courier()->create();
        $owner = User::factory()->owner()->create();

        $assignment = $this->service()->assign($device, $courier, $owner);
        $closed = $this->service()->unassign($device, $owner, 'return device');

        $this->assertNotNull($closed);
        $this->assertNotNull($closed->unassigned_at);
        $this->assertSame($owner->id, $closed->unassigned_by_user_id);
        $this->assertStringContainsString('return device', (string) $closed->notes);
        $this->assertSame($assignment->id, $closed->id);
    }

    public function test_unassign_is_idempotent_when_no_open_row(): void
    {
        $device = Device::factory()->create();
        $owner = User::factory()->owner()->create();

        $result = $this->service()->unassign($device, $owner);

        $this->assertNull($result);
    }

    public function test_history_preserved_across_multiple_reassignments(): void
    {
        $device = Device::factory()->create();
        $courierA = User::factory()->courier()->create();
        $courierB = User::factory()->courier()->create();
        $courierC = User::factory()->courier()->create();
        $owner = User::factory()->owner()->create();

        $this->service()->assign($device, $courierA, $owner);
        $this->service()->assign($device, $courierB, $owner);
        $this->service()->assign($device, $courierC, $owner);

        $rows = DeviceAssignment::query()
            ->where('device_id', $device->id)
            ->orderBy('assigned_at')
            ->orderBy('id')
            ->get();

        $this->assertCount(3, $rows);
        $this->assertNotNull($rows[0]->unassigned_at);
        $this->assertNotNull($rows[1]->unassigned_at);
        $this->assertNull($rows[2]->unassigned_at);
        $this->assertSame($courierC->id, $rows[2]->courier_id);
    }

    public function test_freed_courier_can_be_bound_to_a_new_device(): void
    {
        $deviceOne = Device::factory()->create();
        $deviceTwo = Device::factory()->create();
        $courier = User::factory()->courier()->create();
        $owner = User::factory()->owner()->create();

        $this->service()->assign($deviceOne, $courier, $owner);
        $this->service()->unassign($deviceOne, $owner);
        $reassigned = $this->service()->assign($deviceTwo, $courier, $owner);

        $this->assertSame($deviceTwo->id, $reassigned->device_id);
        $this->assertNull($reassigned->unassigned_at);
    }
}
