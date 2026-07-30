<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domain\Customer\CustomerPhone;
use App\Models\Customer;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Customer>
 */
class CustomerFactory extends Factory
{
    protected $model = Customer::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        // A modest lat/lng box centred on West Java, matching the deployment
        // region. Factory data is illustrative only and uses Faker.
        $latitude = $this->faker->randomFloat(7, -7.5, -6.0);
        $longitude = $this->faker->randomFloat(7, 106.0, 107.5);

        // Generate a phone that already normalises cleanly: +62 followed
        // by 9-12 digits keeps us well within the 9-15 digit rule.
        $phoneDigits = $this->faker->unique()->numerify('##########');
        $phone = CustomerPhone::normalize('+62'.$phoneDigits);

        return [
            'name' => $this->faker->name(),
            'phone' => $phone,
            'address' => $this->faker->streetAddress(),
            'latitude' => $latitude,
            'longitude' => $longitude,
            'notes' => $this->faker->optional(0.3)->sentence(),
            'is_active' => true,
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn (): array => ['is_active' => false]);
    }
}
