<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Delivery;
use App\Models\Device;
use App\Models\TelemetryRecord;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TelemetryRecord>
 */
class TelemetryRecordFactory extends Factory
{
    protected $model = TelemetryRecord::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $now = now();

        return [
            'device_id' => Device::factory(),
            'delivery_id' => Delivery::factory(),
            'latitude' => fake()->randomFloat(7, -6.4, -6.1),
            'longitude' => fake()->randomFloat(7, 106.6, 106.9),
            'speed_kmh' => fake()->randomFloat(2, 0, 60),
            'heading_degrees' => fake()->randomFloat(2, 0, 360),
            'gps_timestamp' => $now->copy()->subSeconds(5),
            'received_at' => $now,
        ];
    }
}
