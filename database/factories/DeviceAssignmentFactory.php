<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Device;
use App\Models\DeviceAssignment;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<DeviceAssignment>
 */
class DeviceAssignmentFactory extends Factory
{
    protected $model = DeviceAssignment::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'device_id' => Device::factory(),
            'courier_id' => User::factory()->courier(),
            'assigned_at' => now(),
            'unassigned_at' => null,
            'assigned_by_user_id' => User::factory()->owner(),
            'unassigned_by_user_id' => null,
            'notes' => null,
        ];
    }

    public function closed(?\DateTimeInterface $unassignedAt = null): static
    {
        return $this->state(fn (array $attributes) => [
            'unassigned_at' => $unassignedAt ?? now(),
            'unassigned_by_user_id' => User::factory()->owner(),
        ]);
    }
}
