<?php

declare(strict_types=1);

namespace App\Domain\Device;

use App\Domain\Device\Exceptions\CourierAlreadyBoundException;
use App\Domain\Device\Exceptions\InactiveCourierException;
use App\Domain\Device\Exceptions\InactiveDeviceException;
use App\Domain\Device\Exceptions\NotCourierRoleException;
use App\Models\Device;
use App\Models\DeviceAssignment;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Opens, closes, and reassigns Device ↔ courier bindings.
 *
 * Every mutation runs inside a database transaction so the (close old,
 * open new) reassignment pair is atomic. The service is the single
 * enforcement point for AR-50: at most one open row per device and
 * per courier at a time.
 *
 * The service does not touch the current authenticated user directly;
 * the controller passes the acting owner into $performedBy so the
 * assignment history captures who acted, not just when.
 */
class DeviceAssignmentService
{
    /**
     * Bind $courier to $device, creating a new open assignment row.
     *
     * If $device already has an open assignment to the same $courier
     * the current row is returned unchanged (idempotent). If $device
     * has an open assignment to a different courier, that row is
     * closed and a new row is opened in the same transaction. If
     * $courier already holds an open assignment to a different device,
     * the operation is refused via {@see CourierAlreadyBoundException}
     * so the admin has to explicitly release the courier first.
     *
     * @throws InactiveDeviceException     If the device is disabled.
     * @throws NotCourierRoleException     If $courier is not a courier.
     * @throws InactiveCourierException    If the courier is disabled.
     * @throws CourierAlreadyBoundException If the courier is bound elsewhere.
     */
    public function assign(
        Device $device,
        User $courier,
        User $performedBy,
        ?string $notes = null,
    ): DeviceAssignment {
        $this->assertDeviceActive($device);
        $this->assertCourier($courier);

        return DB::transaction(function () use ($device, $courier, $performedBy, $notes) {
            $device->refresh();

            /** @var DeviceAssignment|null $current */
            $current = $device->assignments()
                ->whereNull('unassigned_at')
                ->lockForUpdate()
                ->first();

            if ($current !== null && $current->courier_id === $courier->id) {
                return $current;
            }

            $courierOpen = DeviceAssignment::query()
                ->where('courier_id', $courier->id)
                ->whereNull('unassigned_at')
                ->lockForUpdate()
                ->first();

            if ($courierOpen !== null && $courierOpen->device_id !== $device->id) {
                throw CourierAlreadyBoundException::forCourierId(
                    $courier->id,
                    (int) $courierOpen->device_id,
                );
            }

            if ($current !== null) {
                $this->closeAssignment($current, $performedBy, 'reassigned to another courier');
            }

            return DeviceAssignment::query()->create([
                'device_id' => $device->id,
                'courier_id' => $courier->id,
                'assigned_at' => now(),
                'unassigned_at' => null,
                'assigned_by_user_id' => $performedBy->id,
                'unassigned_by_user_id' => null,
                'notes' => $notes,
            ]);
        });
    }

    /**
     * Close the currently-open assignment for $device, if any.
     *
     * Idempotent: returns null when the device has no open row.
     */
    public function unassign(
        Device $device,
        User $performedBy,
        ?string $reason = null,
    ): ?DeviceAssignment {
        return DB::transaction(function () use ($device, $performedBy, $reason) {
            /** @var DeviceAssignment|null $current */
            $current = $device->assignments()
                ->whereNull('unassigned_at')
                ->lockForUpdate()
                ->first();

            if ($current === null) {
                return null;
            }

            $this->closeAssignment($current, $performedBy, $reason);

            return $current->fresh();
        });
    }

    /**
     * Close a single open assignment row with an audit trail.
     *
     * Callers must already hold a transaction and a row lock on
     * $assignment; this helper only writes the closure fields.
     */
    private function closeAssignment(
        DeviceAssignment $assignment,
        User $performedBy,
        ?string $reason,
    ): void {
        $assignment->forceFill([
            'unassigned_at' => now(),
            'unassigned_by_user_id' => $performedBy->id,
            'notes' => $this->mergeNotes($assignment->notes, $reason),
        ])->save();
    }

    /**
     * Combine any pre-existing notes with a closure reason so history
     * rows keep both the "why assigned" and "why unassigned" context.
     */
    private function mergeNotes(?string $existing, ?string $reason): ?string
    {
        $existing = $existing !== null ? trim($existing) : '';
        $reason = $reason !== null ? trim($reason) : '';

        if ($existing === '' && $reason === '') {
            return null;
        }

        if ($existing === '') {
            return sprintf('Closed: %s', $reason);
        }

        if ($reason === '') {
            return $existing;
        }

        return sprintf('%s | Closed: %s', $existing, $reason);
    }

    private function assertDeviceActive(Device $device): void
    {
        if (! $device->isActive()) {
            throw InactiveDeviceException::forDeviceId((int) $device->id);
        }
    }

    private function assertCourier(User $courier): void
    {
        if (! $courier->isCourier()) {
            throw NotCourierRoleException::forUserId((int) $courier->id);
        }

        if (! (bool) $courier->is_active) {
            throw InactiveCourierException::forUserId((int) $courier->id);
        }
    }
}
