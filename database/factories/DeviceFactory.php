<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Device;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Device>
 */
class DeviceFactory extends Factory
{
    protected $model = Device::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'identifier' => strtoupper(Str::random(3)).'-'.strtoupper(Str::random(6)),
            'model' => fake()->randomElement(['Concox GT06', 'Teltonika FMB920', 'Queclink GV75']),
            'hardware_version' => 'v'.fake()->numberBetween(1, 5).'.'.fake()->numberBetween(0, 9),
            'api_token' => Str::random(40),
            'is_active' => true,
            'last_seen_at' => null,
            'notes' => null,
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => false,
        ]);
    }

    public function withToken(string $token): static
    {
        return $this->state(fn (array $attributes) => [
            'api_token' => $token,
        ]);
    }

    public function lastSeenAt(\DateTimeInterface $when): static
    {
        return $this->state(fn (array $attributes) => [
            'last_seen_at' => $when,
        ]);
    }
}
