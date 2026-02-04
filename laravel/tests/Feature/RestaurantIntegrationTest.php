<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\Driver;
use App\Models\Restaurant;
use App\Services\RestaurantService;
use Illuminate\Foundation\Testing\RefreshDatabase;

class RestaurantIntegrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_full_dispatch_cycle_updates_database()
    {
        $restaurant = Restaurant::factory()->create(['orders_count' => 10]);
        $driver = Driver::factory()->create(['capacity' => 5]);

        $service = app(RestaurantService::class);

        $assignment = $service->solve(collect([$driver]), collect([$restaurant]));
        $service->updateDatabaseAfterSolve(collect([$restaurant]), $assignment);

        $restaurant->refresh();

        $this->assertEquals(6, $restaurant->orders_count);
        $this->assertDatabaseHas('restaurants', [
            'id' => $restaurant->id,
            'orders_count' => 6
        ]);
    }
}
