<?php

namespace Tests\Unit;

use Tests\TestCase;
use App\Services\GeoService;
use App\Services\RestaurantService;
use Mockery\MockInterface;

class restaurantServiceTest extends TestCase
{
    private GeoService $geoService;
    private RestaurantService $restaurantService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->geoService = new GeoService();
        $this->restaurantService = new RestaurantService($this->geoService);
    }

    /**
     * Check how solve() is working.
     */
    public function test_solve_assigns_drivers_to_restaurants(): void
    {
        $driver = $this->createMockDriver(1, 'Driver 1', 42.6966060, 23.3204766, 3);

        $restaurant = $this->createMockRestaurant(1, 'Restaurant 1', 42.6966060, 23.3204766, 10);

        $drivers = collect([$driver]);
        $restaurants = collect([$restaurant]);

        $assignments = $this->restaurantService->solve($drivers, $restaurants);

        $this->assertCount(1, $assignments);
        $this->assertEquals(1, $assignments[0]['driver_id']);
        $this->assertEquals(1, $assignments[0]['restaurant_id']);
        $this->assertEquals(3, $assignments[0]['orders_assigned']);
        $this->assertEquals(7, $restaurant->orders_count, "Restaurant should have 7 orders left after driver picks up 3.");
    }

    private function createMockDriver(
        int $id,
        string $name,
        float $lat,
        float $lng,
        int $capacity
    ): MockInterface
    {
        $driver = \Mockery::mock();
        $driver->id = $id;
        $driver->name = $name;
        $driver->lat = $lat;
        $driver->lng = $lng;
        $driver->capacity = $capacity;
        $driver->orders_count = 0;

        return $driver;
    }

    private function createMockRestaurant(
        int $id,
        string $title,
        float $lat,
        float $lng,
        int $ordersCount
    ): MockInterface
    {
        $restaurant = \Mockery::mock();
        $restaurant->id = $id;
        $restaurant->title = $title;
        $restaurant->lat = $lat;
        $restaurant->lng = $lng;
        $restaurant->orders_count = $ordersCount;

        return $restaurant;
    }

    /**
     * Test if ORDER_WEIGHT is respected.
     */
    public function test_solve_prioritizes_restaurants_with_more_orders(): void
    {
        $driver = $this->createMockDriver(1, 'Driver 1', 42.6966060, 23.3204766, 10);

        $closerRestaurant = $this->createMockRestaurant(1, 'Closer Restaurant', 42.6966060, 23.3204766, 2);
        $furtherRestaurant = $this->createMockRestaurant(2, 'Further Restaurant', 42.6966060 + 0.02, 23.3204766, 20);

        $drivers = collect([$driver]);
        $restaurants = collect([$closerRestaurant, $furtherRestaurant]);

        $assignments = $this->restaurantService->solve($drivers, $restaurants);

// dd(
//     $assignments
// );
// .Illuminate\Support\Collection^ {#1120
//   #items: array:1 [
//     0 => array:9 [
//       "driver_id" => 1
//       "driver_name" => "Driver 1"
//       "lat" => 42.696606
//       "lng" => 23.3204766
//       "restaurant_id" => 2
//       "restaurant_title" => "Far Restaurant"
//       "orders_assigned" => 4
//       "distance" => 2.2238985328915
//       "score" => -7.7761014671085
//     ]
//   ]
//   #escapeWhenCastingToString: false
// }

        $this->assertEquals(2, $assignments[0]['restaurant_id']);
        $this->assertEquals(4, $assignments[0]['orders_assigned']);
    }

    public function test_solve_skips_empty_restaurants_and_unassigned_drivers(): void
    {
        $driver1 = $this->createMockDriver(1, 'Driver 1', 42.6966060, 23.3204766, 3);
        $driver2 = $this->createMockDriver(2, 'Driver 2', 42.6966060 + 0.01, 23.3204766, 3);

        $restaurant = $this->createMockRestaurant(1, 'Restaurant 1', 42.6966060, 23.3204766, 2);

        $drivers = collect([$driver1, $driver2]);
        $restaurants = collect([$restaurant]);

        $assignments = $this->restaurantService->solve($drivers, $restaurants);

// dd(
//     $assignments,
//     $restaurant->orders_count//just `$restaurant` returns whole mock object
// );

        $this->assertCount(1, $assignments);
        $this->assertEquals(0, $restaurant->orders_count);
    }
}
