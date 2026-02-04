<?php

namespace App\Services;

use App\Models\Driver;
use App\Models\Restaurant;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

class RestaurantService
{
    private const DISTANCE_WEIGHT = 1.0;
    private const ORDER_WEIGHT = 0.5;

    private const MIN_ORDERS_COUNT_PER_RESTAURANT = 5;
    private const MAX_ORDERS_COUNT_PER_RESTAURANT = 50;

    private const MIN_ORDERS_PER_DRIVER = 1;
    private const MAX_ORDERS_PER_DRIVER = 4;

    public function __construct(protected GeoService $geoService)
    {
        //
    }

    public function randomize(): void
    {
        $restaurants = Restaurant::all();

        foreach ($restaurants as $restaurant) {
            $restaurant->update(['orders_count' => rand(self::MIN_ORDERS_COUNT_PER_RESTAURANT, self::MAX_ORDERS_COUNT_PER_RESTAURANT)]);
        }

        foreach (Driver::all() as $driver) {
            // Get randomly a restaurant from the collection.
            $base = $restaurants->random();

            /**
             * Put a driver from -0.05 to 0.05 of a restaurant.
             * 0.001 degrees is roughly equal to 111 meters at the equator.
             * 50/1000 = 0.05 ~ 5.55km
             *
             * "id" => 7
             * "lat" => "42.6966060"
             * "lng" => "23.3204766"
             */
            $driver->update([
                'lat' => $base->lat + (rand(-50, 50) / 1000),
                'lng' => $base->lng + (rand(-50, 50) / 1000),
                'capacity' => rand(self::MIN_ORDERS_PER_DRIVER, self::MAX_ORDERS_PER_DRIVER),
                'next_restaurant_id' => 0
            ]);
        }
    }

    public function generateFullReport(): array
    {
        $restaurants = Restaurant::all();
        $drivers = Driver::all();

        $before = $restaurants->map(function($r) {
            return [
                'id' => $r->id,
                'title' => $r->title,
                'orders_count' => $r->orders_count,
                'lat' => $r->lat,
                'lng' => $r->lng,
            ];
        })->toArray();

        $assignments = $this->solve($drivers, $restaurants);

        return $this->formatReportData($before, $restaurants, $drivers, $assignments);
    }

    public function solve(Collection $drivers, Collection $restaurants): Collection
    {
        $assignments = collect();

        $activeRestaurants = $restaurants->where('orders_count', '>', 0);

        foreach ($drivers as $driver) {
            if ($activeRestaurants->sum('orders_count') <= 0) break;

            $match = $this->findBestMatch($driver, $activeRestaurants);

            if ($match) {
                $restaurant = $match['restaurant'];
                $take = min($driver->capacity, $restaurant->orders_count, self::MAX_ORDERS_PER_DRIVER);

                $restaurant->orders_count -= $take;

                $assignments->push([
                    'driver_id' => $driver->id,
                    'driver_name' => $driver->name,
                    'lat' => $driver->lat,
                    'lng' => $driver->lng,
                    'restaurant_id' => $restaurant->id,
                    'restaurant_title' => $restaurant->title,
                    'orders_assigned' => $take,
                    'distance' => $match['distance'],
                    'score' => $match['score']
                ]);
            }
        }

// dd(
//     $assignments
// );

        return $assignments;
    }

    private function findBestMatch($driver, Collection $restaurants): ?array
    {
        $bestScore = PHP_FLOAT_MAX;
        $selected = null;
        $bestDist = 0;

        foreach ($restaurants as $restaurant) {
            if ($restaurant->orders_count <= 0) {
                continue;
            }

            $distance = $this->geoService->distance(
                $driver->lat,
                $driver->lng,
                $restaurant->lat,
                $restaurant->lng
            );

            /**
             * The algorithm always picks the restaurant with the lowest score and most orders firstly.
             */
            $score = (self::DISTANCE_WEIGHT * $distance) - (self::ORDER_WEIGHT * $restaurant->orders_count);

            if ($score < $bestScore) {//-3 < max
                $bestScore = $score;
                $selected = $restaurant;
                $bestDist = $distance;
            }

// Log::debug(
// "\$driver->id: ".
// $driver->id.
// ", \$restaurant->id: ".
// $restaurant->id.
// ", \$distance: ".
// $distance.
// ", \$score: ".
// $score.
// ", \$bestScore: ".
// $bestScore.
// ", \$bestDist: ".
// $bestDist
// );

        }

// exit();

        return $selected ? [
                'restaurant' => $selected,
                'score' => $bestScore,
                'distance' => $bestDist
            ] : null;
    }

    private function formatReportData($before, $restaurantsAfter, $allDrivers, $assignments): array
    {
        $assignments = collect($assignments);
        $assignmentMap = $assignments->keyBy('driver_id');

        $driverData = $allDrivers->map(fn($driver) => [
            'id' => $driver->id,
            'name' => $driver->name,
            'position' => ['lat' => $driver->lat, 'lng' => $driver->lng],
            'assigned_restaurant_id' => $assignmentMap[$driver->id]['restaurant_id'] ?? 0,
            'assigned_restaurant_title' => $assignmentMap[$driver->id]['restaurant_title'] ?? 'Unassigned',
            'orders_assigned' => $assignmentMap[$driver->id]['orders_assigned'] ?? 0,
            'distance_to_assigned' => $assignmentMap[$driver->id]['distance'] ?? 0,
            'assignment_score' => $assignmentMap[$driver->id]['score'] ?? 0,
        ]);

// dd(
//     $before,
//     $restaurantsAfter
// );

        return [
            'restaurants_before' => $before,
            'restaurants_after' => $restaurantsAfter->toArray(),
            'drivers' => $driverData->toArray(),
            'stats' => [
                'total_drivers_assigned' => $assignments->count(),
                'total_distance' => $assignments->sum('distance'),
                'average_distance' => $assignments->avg('distance') ?? 0,
                'total_orders_assigned' => $assignments->sum('orders_assigned'),
                'total_orders_remaining' => $restaurantsAfter->sum('orders_count'),
                'utilization_rate' => $allDrivers->sum('capacity') > 0
                    ? ($assignments->sum('orders_assigned') / $allDrivers->sum('capacity')) * 100
                    : 0
            ]
        ];
    }
}
