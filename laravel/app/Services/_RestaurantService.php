<?php

namespace App\Services;

use App\Models\Driver;
use App\Models\Restaurant;
use Illuminate\Support\Collection;

class _RestaurantService
{
    private const DISTANCE_WEIGHT = 1.0;
    private const ORDER_WEIGHT = 0.5;
    private const MAX_ORDERS_PER_DRIVER = 4;

    /**
     * Create a new class instance.
     */
    public function __construct(protected GeoService $geoService)
    {
        //
    }

    // randomize()
    public function randomize(): void
    {
        $restaurants = Restaurant::all();

        foreach ($restaurants as $restaurant) {
            $restaurant->update(['orders_count' => rand(5, 50)]);
        }

        foreach (Driver::all() as $driver) {
            // Pick a random restaurant to spawn near
            $base = $restaurants->random();

            // Generate a random point within ~5km
            $driver->update([
                'lat' => $base->lat + (rand(-50, 50) / 1000),
                'lng' => $base->lng + (rand(-50, 50) / 1000),
                'capacity' => rand(1, 4),
                'next_restaurant_id' => 0
            ]);
        }
    }

    public function solve(): array
    {
        $drivers = Driver::all();
        $restaurants = Restaurant::where('orders_count', '>', 0)->get();

        $assignments = [];

        while($drivers->isNotEmpty() && $restaurants->sum('orders_count') > 0) {
            $driver = $drivers->shift();

            $bestMatch = $this->findBestMatch($driver, $restaurants);

            if($bestMatch) {
                $restaurant = $bestMatch['restaurant'];

                $take = min($driver->capacity, $restaurant->orders_count, SELF::MAX_ORDERS_PER_DRIVER);

                $restaurant->orders_count = $restaurant->orders_count - $take;

                $assignments[] = array(
                    'driver_id' => $driver->id,
                    'restaurant_id' => $restaurant->id,
                    'restaurant_title' => $restaurant->title,
                    'orders_assigned' => $take,
                    'distance' => $bestMatch['distance'],
                    'score' => $bestMatch['score'],
                );
            }
        }

        return $assignments;
    }

    private function findBestMatch(Driver $driver, Collection $restaurants): ?array
    {
        $bestScore = PHP_FLOAT_MAX;
        $selected = null;

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

            $score = (self::DISTANCE_WEIGHT * $distance) - (self::ORDER_WEIGHT * $restaurant->orders_count);

            if ($score < $bestScore) {
                $bestScore = $score;
                $selected = $restaurant;
            }
        }

        return $selected ? [
                'restaurant' => $selected,
                'score' => $bestScore,
                'distance' => $distance
            ] : null;
    }


    public function generateFullReport(): array
    {
        $restaurants = Restaurant::all();
        $drivers = Driver::all();

        $before = $restaurants->map(fn($r) => $r->replicate()->toArray())->toArray();

        $assignments = $this->solve();

        $restaurantsAfter = Restaurant::all()->map(fn($r) => $r->toArray())->toArray();

        return $this->formatReportData($before, $restaurants, $drivers, $assignments);
    }

    private function formatReportData($before, $restaurantsAfter, $allDrivers, $assignments): array
    {
        // Wrap assignments once so we can use sum(), avg(), etc. easily
        $assignments = collect($assignments);
        $assignmentMap = $assignments->keyBy('driver_id');

        // 1. Map Drivers (The "Factory")
        $driverData = $allDrivers->map(fn($driver) => [
            'id' => $driver->id,
            'name' => $driver->name,
            'position' => ['lat' => $driver->lat, 'lng' => $driver->lng],
            'assigned_restaurant_id' => $assignmentMap[$driver->id]['restaurant_id'] ?? 0,
            'assigned_restaurant_title' => $assignmentMap[$driver->id]['restaurant_title'] ?? 'Unassigned',
            'orders_assigned' => $assignmentMap[$driver->id]['orders_assigned'] ?? 0,
            'distance_to_assigned' => $assignmentMap[$driver->id]['distance'] ?? 0,
        ]);

        // 2. Final Report Assembly
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
