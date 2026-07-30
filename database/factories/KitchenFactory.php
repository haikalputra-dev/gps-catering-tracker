<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Kitchen;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Kitchen>
 */
class KitchenFactory extends Factory
{
    protected $model = Kitchen::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        // A modest lat/lng box centred on West Java, matching the deployment
        // region. Factory data is illustrative only.
        $latitude = $this->faker->randomFloat(7, -7.5, -6.0);
        $longitude = $this->faker->randomFloat(7, 106.0, 107.5);

        return [
            'code' => strtoupper($this->faker->unique()->bothify('KIT-####')),
            'name' => 'Kitchen ' . $this->faker->unique()->numberBetween(1, 99999),
            'address' => $this->faker->streetAddress(),
            'phone' => $this->faker->optional(0.7)->numerify('+62##########'),
            'latitude' => $latitude,
            'longitude' => $longitude,
            'is_active' => true,
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn (): array => ['is_active' => false]);
    }
}
