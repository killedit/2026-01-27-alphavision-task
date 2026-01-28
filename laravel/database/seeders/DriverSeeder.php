<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class DriverSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Restaurant coordinates for reference (Sofia area)
        $restaurantCoordinates = [
            ['lat' => 42.667122, 'lng' => 23.281657],
            ['lat' => 42.688600, 'lng' => 23.308027],
            ['lat' => 42.670071, 'lng' => 23.313399],
            ['lat' => 42.692017, 'lng' => 23.326259],
            ['lat' => 42.6982608, 'lng' => 23.3078595],
            ['lat' => 42.6481687, 'lng' => 23.3793724],
            ['lat' => 42.696606, 'lng' => 23.3204766],
            ['lat' => 42.713895, 'lng' => 23.264476],
            ['lat' => 42.6570524, 'lng' => 23.3142243],
            ['lat' => 42.673136, 'lng' => 23.348732]
        ];

        $drivers = [];

        for ($i = 1; $i <= 100; $i++) {
            // Pick a random restaurant
            $randomRestaurant = $restaurantCoordinates[array_rand($restaurantCoordinates)];

            // Generate random position within 5km of the restaurant
            $position = $this->randomPointNear($randomRestaurant['lat'], $randomRestaurant['lng'], 5);

            $drivers[] = [
                'name' => "Driver {$i}",
                'lat' => $position['lat'],
                'lng' => $position['lng'],
                'capacity' => rand(1, 4),
                'next_restaurant_id' => 0,
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }

        // Insert drivers in chunks
        foreach (array_chunk($drivers, 20) as $chunk) {
            DB::table('drivers')->insert($chunk);
        }
    }

    /**
     * Generate random point near given coordinates within max distance (in km)
     */
    private function randomPointNear($lat, $lng, $maxKm = 5)
    {
        $radius = $maxKm / 111; // Approximate degrees per km

        $u = mt_rand() / mt_getrandmax();
        $v = mt_rand() / mt_getrandmax();
        $w = $radius * sqrt($u);
        $t = 2 * M_PI * $v;

        return [
            'lat' => $lat + $w * cos($t),
            'lng' => $lng + $w * sin($t)
        ];
    }
}
