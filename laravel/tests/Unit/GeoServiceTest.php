<?php

namespace Tests\Unit;

use Tests\TestCase;
use App\Services\GeoService;

class GeoServiceTest extends TestCase
{
    private GeoService $geoService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->geoService = new GeoService();
    }

    /**
     * Test 0 distance.
     */
    public function test_zero_distance_for_the_same_coordinates(): void
    {
        $distance = $this->geoService->distance(
            42.69751,
            23.32415,
            42.69751,
            23.32415
        );

        $this->assertEquals(
            0,
            $distance,
            'Distance must be 0!',
        );
    }

    /**
     * Test distance between two known points.
     * https://latitude.to/map/bg/bulgaria/cities
     * https://www.distancecalculator.net/from-sofia-to-stara-zagora
     */
    public function test_distance_calculation_is_accurate(): void
    {
        // Sofia
        $lat1 = 42.69751;
        $lng1 = 23.32415;

        // Stara Zagora
        $lat2 = 42.43278;
        $lng2 = 25.64194;

        $distance = $this->geoService->distance(
            $lat1,
            $lng1,
            $lat2,
            $lng2
        );

        $this->assertGreaterThan(190, $distance, "Distance is greater than 190km.");
        $this->assertLessThan(200, $distance, "Distance is les than 200km.");
    }
}
