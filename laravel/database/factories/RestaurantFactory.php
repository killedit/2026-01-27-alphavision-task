<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Restaurant>
 */
class RestaurantFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'title' => $this->faker->company(),
            'lat' => $this->faker->latitude(42.0, 43.0),
            'lng' => $this->faker->longitude(23.0, 24.0),
            'orders_count' => $this->faker->numberBetween(5, 50),
        ];
    }
}
