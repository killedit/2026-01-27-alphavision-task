<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Driver>
 */
class DriverFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => 'Driver ' . $this->faker->name(),
            'lat' => $this->faker->latitude(42.0, 43.0),
            'lng' => $this->faker->longitude(23.0, 24.0),
            'capacity' => $this->faker->numberBetween(1, 4),
            'next_restaurant_id' => 0,
        ];
    }
}
