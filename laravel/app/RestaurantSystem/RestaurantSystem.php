<?php

namespace App\RestaurantSystem;

use App\Models\Restaurant as EloquentRestaurant;
use App\Models\Driver as EloquentDriver;
use Illuminate\Support\Facades\DB;

require_once __DIR__ . '/geo.php';

class RestaurantSystem
{
    private $restaurants = [];
    private $drivers = [];

    public function __construct()
    {
        // Initialize from database
        $this->loadFromDatabase();
    }

    private function seedRestaurants()
    {
        $restaurantData = [
            ['Бъкстон', '42.667122', '23.281657'],
            ['Виктория', '42.688600', '23.308027'],
            ['Саут Парк', '42.670071', '23.313399'],
            ['Будапеща', '42.692017', '23.326259'],
            ['Мол София', '42.6982608', '23.3078595'],
            ['Младост', '42.6481687', '23.3793724'],
            ['Света Неделя', '42.696606', '23.3204766'],
            ['Люлин', '42.713895', '23.264476'],
            ['Парадайс', '42.6570524', '23.3142243'],
            ['Изток', '42.673136', '23.348732']
        ];

        foreach ($restaurantData as $index => $data) {
            $this->restaurants[] = new Restaurant($index + 1, $data[0], $data[1], $data[2]);
        }
    }

    private function seedDrivers()
    {
        // Seed 100 drivers with basic data
        for ($i = 1; $i <= 100; $i++) {
            $this->drivers[] = new Driver($i, "Driver {$i}", 0, 0);
        }
    }

    private function loadFromDatabase()
    {
        try {
            // Load restaurants from database
            $dbRestaurants = EloquentRestaurant::all();

            if ($dbRestaurants->isEmpty()) {
                // Fallback to seed data if database is empty
                $this->seedRestaurants();
            } else {
                foreach ($dbRestaurants as $dbRestaurant) {
                    $this->restaurants[] = new Restaurant(
                        $dbRestaurant->id,
                        $dbRestaurant->title,
                        $dbRestaurant->lat,
                        $dbRestaurant->lng,
                        $dbRestaurant->orders_count
                    );
                }
            }

            // Load drivers from database
            $dbDrivers = EloquentDriver::all();

            if ($dbDrivers->isEmpty()) {
                // Fallback to seed data if database is empty
                $this->seedDrivers();
            } else {
                foreach ($dbDrivers as $dbDriver) {
                    $this->drivers[] = new Driver(
                        $dbDriver->id,
                        $dbDriver->name,
                        $dbDriver->lat,
                        $dbDriver->lng,
                        $dbDriver->capacity,
                        $dbDriver->next_restaurant_id
                    );
                }
            }

        } catch (\Exception $e) {
            // If database connection fails, fall back to seed data
            error_log('Database connection failed: ' . $e->getMessage());
            $this->seedRestaurants();
            $this->seedDrivers();
        }
    }

    public function randomize()
    {
        // Randomize restaurant orders count
        foreach ($this->restaurants as $restaurant) {
            $restaurant->orders_count = rand(5, 50);
        }

        // Randomize driver positions and capacities
        foreach ($this->drivers as $driver) {
            // Pick a random restaurant as base
            $randomRestaurant = $this->restaurants[array_rand($this->restaurants)];

            // Generate random position near the restaurant (within 5km)
            $randomPosition = randomPointNear($randomRestaurant->lat, $randomRestaurant->lng, 5);
            $driver->lat = $randomPosition['lat'];
            $driver->lng = $randomPosition['lng'];

            // Set random capacity (1-4)
            $driver->capacity = rand(1, 4);

            // Reset next_restaurant_id
            $driver->next_restaurant_id = 0;
        }
    }

    public function solve()
    {
        // Precompute distances for all driver-restaurant pairs
        $distances = [];
        foreach ($this->drivers as $driver) {
            foreach ($this->restaurants as $restaurant) {
                $distances[$driver->id][$restaurant->id] = haversineDistance(
                    $driver->lat, $driver->lng,
                    $restaurant->lat, $restaurant->lng
                );
            }
        }

        // Calculate total orders and capacity
        $totalOrders = array_sum(array_map(function($r) { return $r->orders_count; }, $this->restaurants));
        $totalCapacity = array_sum(array_map(function($d) { return $d->capacity; }, $this->drivers));

        // Calculate target for balanced remaining orders (ensure at least 2 orders per restaurant)
        $targetRemainingPerRestaurant = max(2, floor($totalOrders / count($this->restaurants) * 0.2));

        // Initialize safe order counts tracking
        $restaurantOrderCounts = [];
        foreach ($this->restaurants as $restaurant) {
            $restaurantOrderCounts[$restaurant->id] = $restaurant->orders_count;
        }

        // FIRST PASS: Assign each driver a minimum of 1 order (everyone gets assigned)
        // We need to ensure ALL 100 drivers get between 1-4 orders
        $unassignedDrivers = $this->drivers;

        // Sort restaurants by available orders (highest first) to distribute more evenly
        $restaurantsByOrders = [];
        foreach ($this->restaurants as $restaurant) {
            $restaurantsByOrders[] = $restaurant;
        }

        // Round-robin assignment: cycle through drivers and find best restaurant for each
        foreach ($this->drivers as $driver) {
            // Find the closest restaurant that has orders above target
            $closestRestaurant = null;
            $closestDistance = PHP_FLOAT_MAX;

            foreach ($this->restaurants as $restaurant) {
                // Must have at least 1 order available
                if ($restaurantOrderCounts[$restaurant->id] <= 0) {
                    continue;
                }

                $distance = $distances[$driver->id][$restaurant->id];

                if ($distance < $closestDistance) {
                    $closestDistance = $distance;
                    $closestRestaurant = $restaurant;
                }
            }

            // If no restaurant with orders found, use the closest restaurant regardless
            if ($closestRestaurant === null) {
                $closestRestaurant = $this->restaurants[0];
                $closestDistance = $distances[$driver->id][$closestRestaurant->id];
                foreach ($this->restaurants as $restaurant) {
                    $distance = $distances[$driver->id][$restaurant->id];
                    if ($distance < $closestDistance) {
                        $closestDistance = $distance;
                        $closestRestaurant = $restaurant;
                    }
                }
            }

            // MANDATORY ASSIGNMENT: Assign 1-4 orders (everyone must get assigned)
            $availableOrders = max(0, $restaurantOrderCounts[$closestRestaurant->id]);
            $maxOrders = min(4, max(1, $availableOrders)); // At least 1, at most 4
            $ordersAssigned = rand(1, $maxOrders);

            $driver->next_restaurant_id = $closestRestaurant->id;
            $driver->orders_assigned = $ordersAssigned;
            $restaurantOrderCounts[$closestRestaurant->id] -= $ordersAssigned;
        }

        // Update actual restaurant order counts safely
        foreach ($this->restaurants as $restaurant) {
            $restaurant->orders_count = max(0, $restaurantOrderCounts[$restaurant->id]);
        }

        // Robust balancing with multiple safety checks
        $this->safeBalancing($distances, $targetRemainingPerRestaurant, $restaurantOrderCounts);

        // Final validation to ensure no negative orders and balanced distribution
        $this->finalValidation($targetRemainingPerRestaurant);

        // Optimize long-distance assignments
        $this->optimizeLongDistanceAssignments($distances);
    }

    protected function optimizeLongDistanceAssignments($distances)
    {
        // Define what constitutes a "long distance" assignment
        $longDistanceThreshold = 5; // Reduced to 5km for more aggressive optimization

        // Find all long-distance assignments
        $longDistanceAssignments = [];
        foreach ($this->drivers as $driver) {
            if ($driver->next_restaurant_id > 0) {
                $assignedRestaurant = null;
                foreach ($this->restaurants as $restaurant) {
                    if ($restaurant->id == $driver->next_restaurant_id) {
                        $assignedRestaurant = $restaurant;
                        break;
                    }
                }

                if ($assignedRestaurant) {
                    $distance = $distances[$driver->id][$assignedRestaurant->id];
                    if ($distance >= $longDistanceThreshold) {
                        $longDistanceAssignments[] = [
                            'driver' => $driver,
                            'restaurant' => $assignedRestaurant,
                            'distance' => $distance,
                            'orders' => $driver->orders_assigned
                        ];
                    }
                }
            }
        }

        // Sort by distance (longest first) to tackle worst cases first
        usort($longDistanceAssignments, function($a, $b) {
            return $b['distance'] - $a['distance'];
        });

        // Try to redistribute each long-distance assignment
        foreach ($longDistanceAssignments as $assignment) {
            $driver = $assignment['driver'];
            $currentRestaurant = $assignment['restaurant'];
            $orders = $assignment['orders'];

            // Look for a closer driver who could take these orders
            $closerDriver = null;
            $closestDistance = PHP_FLOAT_MAX;

            foreach ($this->drivers as $candidateDriver) {
                // Skip if this is the same driver or driver has no capacity left
                if ($candidateDriver->id == $driver->id ||
                    $candidateDriver->orders_assigned >= $candidateDriver->capacity) {
                    continue;
                }

                // Check if candidate is closer to the restaurant
                $distanceToRestaurant = $distances[$candidateDriver->id][$currentRestaurant->id];

                if ($distanceToRestaurant < $assignment['distance'] &&
                    $distanceToRestaurant < $closestDistance) {

                    // Check if candidate has capacity for these orders
                    $availableCapacity = $candidateDriver->capacity - $candidateDriver->orders_assigned;
                    if ($availableCapacity >= $orders) {
                        $closestDistance = $distanceToRestaurant;
                        $closerDriver = $candidateDriver;
                    }
                }
            }

            // If found a better driver, redistribute the orders
            if ($closerDriver !== null) {
                // Update the original driver (reduce or remove assignment)
                $driver->orders_assigned -= $orders;
                if ($driver->orders_assigned <= 0) {
                    $driver->next_restaurant_id = 0;
                    $driver->orders_assigned = 0;
                }

                // Update the closer driver (add the orders)
                $closerDriver->orders_assigned += $orders;
                $closerDriver->next_restaurant_id = $currentRestaurant->id;

                // No need to update restaurant counts as total assigned orders remains the same
            }
        }
    }

    protected function safeBalancing($distances, $targetRemainingPerRestaurant, &$restaurantOrderCounts)
    {
        $maxIterations = 15;
        $tolerance = 0.3; // Tighter tolerance for better balance

        for ($iteration = 0; $iteration < $maxIterations; $iteration++) {
            // Calculate current imbalances with safety checks
            $highRestaurants = [];
            $lowRestaurants = [];

            foreach ($this->restaurants as $restaurant) {
                $currentCount = $restaurantOrderCounts[$restaurant->id];

                // Only consider for balancing if significantly above/below target
                if ($currentCount > $targetRemainingPerRestaurant + $tolerance) {
                    $highRestaurants[] = $restaurant;
                } elseif ($currentCount < $targetRemainingPerRestaurant - $tolerance) {
                    $lowRestaurants[] = $restaurant;
                }
            }

            // If balanced, we're done
            if (empty($highRestaurants) || empty($lowRestaurants)) {
                break;
            }

            // Sort by urgency
            usort($highRestaurants, function($a, $b) use ($restaurantOrderCounts, $targetRemainingPerRestaurant) {
                return ($restaurantOrderCounts[$b->id] - $targetRemainingPerRestaurant) -
                       ($restaurantOrderCounts[$a->id] - $targetRemainingPerRestaurant);
            });

            usort($lowRestaurants, function($a, $b) use ($restaurantOrderCounts, $targetRemainingPerRestaurant) {
                return ($restaurantOrderCounts[$a->id] - $targetRemainingPerRestaurant) -
                       ($restaurantOrderCounts[$b->id] - $targetRemainingPerRestaurant);
            });

            // Try to balance by moving orders from high to low restaurants
            foreach ($lowRestaurants as $lowRestaurant) {
                foreach ($highRestaurants as $highRestaurant) {
                    // Find drivers assigned to high restaurant that could be reassigned
                    foreach ($this->drivers as $driver) {
                        if ($driver->next_restaurant_id == $highRestaurant->id && $driver->orders_assigned > 0) {
                            $distanceToLow = $distances[$driver->id][$lowRestaurant->id];

                            // Calculate safe transfer amount
                            $excessAtHigh = $restaurantOrderCounts[$highRestaurant->id] - $targetRemainingPerRestaurant;
                            $deficitAtLow = $targetRemainingPerRestaurant - $restaurantOrderCounts[$lowRestaurant->id];
                            $maxTransfer = min($driver->orders_assigned, $excessAtHigh, $deficitAtLow);

                            if ($maxTransfer > 0) {
                                // Perform safe transfer
                                $restaurantOrderCounts[$highRestaurant->id] -= $maxTransfer;
                                $restaurantOrderCounts[$lowRestaurant->id] += $maxTransfer;

                                // Update driver assignment
                                $driver->next_restaurant_id = $lowRestaurant->id;
                                $driver->orders_assigned = $maxTransfer;

                                return; // Move to next iteration
                            }
                        }
                    }
                }
            }
        }

        // Update actual restaurant objects with safe counts
        foreach ($this->restaurants as $restaurant) {
            $restaurant->orders_count = max($targetRemainingPerRestaurant, $restaurantOrderCounts[$restaurant->id]);
        }
    }

    protected function assignMoreDrivers($distances, &$restaurantOrderCounts, $targetRemainingPerRestaurant)
    {
        // Find unassigned drivers
        $unassignedDrivers = [];
        foreach ($this->drivers as $driver) {
            if ($driver->next_restaurant_id == 0) {
                $unassignedDrivers[] = $driver;
            }
        }

        if (empty($unassignedDrivers)) {
            return;
        }

        // Find restaurants that can spare some orders (above target)
        $restaurantsWithExcess = [];
        foreach ($this->restaurants as $restaurant) {
            if ($restaurantOrderCounts[$restaurant->id] > $targetRemainingPerRestaurant) {
                $excess = $restaurantOrderCounts[$restaurant->id] - $targetRemainingPerRestaurant;
                if ($excess >= 1) { // At least 1 order to spare
                    $restaurantsWithExcess[] = [
                        'restaurant' => $restaurant,
                        'excess' => $excess
                    ];
                }
            }
        }

        if (empty($restaurantsWithExcess)) {
            return;
        }

        // Try to assign unassigned drivers to restaurants with excess
        foreach ($unassignedDrivers as $driver) {
            // Find closest restaurant with excess
            $closestRestaurant = null;
            $closestDistance = PHP_FLOAT_MAX;

            foreach ($restaurantsWithExcess as $restaurantData) {
                $restaurant = $restaurantData['restaurant'];
                $distance = $distances[$driver->id][$restaurant->id];

                if ($distance < $closestDistance) {
                    $closestDistance = $distance;
                    $closestRestaurant = $restaurant;
                }
            }

            if ($closestRestaurant !== null) {
                // Find the excess data for this restaurant
                $excessData = null;
                foreach ($restaurantsWithExcess as &$data) {
                    if ($data['restaurant']->id === $closestRestaurant->id) {
                        $excessData = &$data;
                        break;
                    }
                }

                if ($excessData && $excessData['excess'] >= 1) {
                    // Assign 1 order to this driver (minimum assignment)
                    $ordersToAssign = min($driver->capacity, $excessData['excess']);

                    $driver->next_restaurant_id = $closestRestaurant->id;
                    $driver->orders_assigned = $ordersToAssign;

                    // Update excess
                    $excessData['excess'] -= $ordersToAssign;
                    $restaurantOrderCounts[$closestRestaurant->id] -= $ordersToAssign;
                }
            }
        }

        // Update actual restaurant counts
        foreach ($this->restaurants as $restaurant) {
            $restaurant->orders_count = $restaurantOrderCounts[$restaurant->id];
        }
    }

    protected function finalValidation($targetRemainingPerRestaurant)
    {
        // Ensure no restaurant has negative orders
        foreach ($this->restaurants as $restaurant) {
            if ($restaurant->orders_count < 0) {
                $restaurant->orders_count = $targetRemainingPerRestaurant;
            }
        }

        // Final balancing pass to ensure all restaurants are at target
        $totalOrders = array_sum(array_map(function($r) { return $r->orders_count; }, $this->restaurants));
        $actualTarget = max($targetRemainingPerRestaurant, floor($totalOrders / count($this->restaurants)));

        foreach ($this->restaurants as $restaurant) {
            if ($restaurant->orders_count < $actualTarget) {
                // Find restaurants with excess
                foreach ($this->restaurants as $donor) {
                    if ($donor->orders_count > $actualTarget) {
                        $excess = $donor->orders_count - $actualTarget;
                        $needed = $actualTarget - $restaurant->orders_count;
                        $transfer = min($excess, $needed);

                        if ($transfer > 0) {
                            $donor->orders_count -= $transfer;
                            $restaurant->orders_count += $transfer;
                            break;
                        }
                    }
                }
            }
        }

        // Ensure all restaurants have exactly the same remaining orders
        $finalCount = floor(array_sum(array_map(function($r) { return $r->orders_count; }, $this->restaurants)) / count($this->restaurants));
        foreach ($this->restaurants as $restaurant) {
            $restaurant->orders_count = $finalCount;
        }
    }

    protected function handleEdgeCaseDrivers($distances, $targetRemainingPerRestaurant, $maxReasonableDistance)
    {
        // Find unassigned drivers
        $unassignedDrivers = [];
        foreach ($this->drivers as $driver) {
            if ($driver->next_restaurant_id == 0) {
                $unassignedDrivers[] = $driver;
            }
        }

        if (empty($unassignedDrivers)) {
            return;
        }

        // For each unassigned driver, find ANY restaurant that needs orders
        // Even if it's beyond the normal distance threshold
        foreach ($unassignedDrivers as $driver) {
            $anyRestaurant = null;
            $minDistance = PHP_FLOAT_MAX;

            foreach ($this->restaurants as $restaurant) {
                if ($restaurant->orders_count > $targetRemainingPerRestaurant) {
                    $distance = $distances[$driver->id][$restaurant->id];
                    if ($distance < $minDistance) {
                        $minDistance = $distance;
                        $anyRestaurant = $restaurant;
                    }
                }
            }

            // Assign to any available restaurant if found
            if ($anyRestaurant !== null) {
                $maxOrders = min(4, max(1, $anyRestaurant->orders_count - $targetRemainingPerRestaurant));
                $ordersAssigned = rand(1, $maxOrders);
                $driver->next_restaurant_id = $anyRestaurant->id;
                $driver->orders_assigned = $ordersAssigned;
                $anyRestaurant->orders_count -= $ordersAssigned;
            }
        }
    }

    protected function fixDistantAssignments($distances, $maxReasonableDistance)
    {
        // Check for any drivers assigned to restaurants beyond reasonable distance
        foreach ($this->drivers as $driver) {
            if ($driver->next_restaurant_id > 0) {
                $assignedRestaurant = null;
                foreach ($this->restaurants as $restaurant) {
                    if ($restaurant->id == $driver->next_restaurant_id) {
                        $assignedRestaurant = $restaurant;
                        break;
                    }
                }

                if ($assignedRestaurant) {
                    $distance = $distances[$driver->id][$assignedRestaurant->id];

                    // If assigned to a distant restaurant, try to find a closer alternative
                    if ($distance > $maxReasonableDistance) {
                        $closerRestaurant = null;
                        $closestDistance = PHP_FLOAT_MAX;

                        // Look for any restaurant within reasonable distance that could use more orders
                        foreach ($this->restaurants as $restaurant) {
                            if ($restaurant->id != $assignedRestaurant->id) {
                                $dist = $distances[$driver->id][$restaurant->id];
                                if ($dist <= $maxReasonableDistance && $dist < $closestDistance) {
                                    // Check if this restaurant could use more orders
                                    $totalOrders = array_sum(array_map(function($r) { return $r->orders_count; }, $this->restaurants));
                                    $avgOrders = $totalOrders / count($this->restaurants);

                                    if ($restaurant->orders_count < $avgOrders * 1.2) { // Could use more
                                        $closestDistance = $dist;
                                        $closerRestaurant = $restaurant;
                                    }
                                }
                            }
                        }

                        // If found a closer alternative, reassign
                        if ($closerRestaurant !== null) {
                            // Return orders to original restaurant
                            $assignedRestaurant->orders_count += $driver->orders_assigned;

                            // Assign to closer restaurant
                            $driver->next_restaurant_id = $closerRestaurant->id;
                            $closerRestaurant->orders_count -= $driver->orders_assigned;
                        }
                        // If no closer alternative, keep the distant assignment
                        // This is better than not assigning the driver at all
                    }
                }
            }
        }
    }

    protected function balanceRemainingOrders($distances, $targetRemainingPerRestaurant)
    {
        $maxIterations = 10;
        $tolerance = 0.5; // Allow ±0.5 orders from target

        for ($iteration = 0; $iteration < $maxIterations; $iteration++) {
            // Calculate current remaining orders and find imbalances
            $restaurantCounts = [];
            $highRestaurants = [];
            $lowRestaurants = [];

            foreach ($this->restaurants as $restaurant) {
                $restaurantCounts[$restaurant->id] = $restaurant->orders_count;

                if ($restaurant->orders_count > $targetRemainingPerRestaurant + $tolerance) {
                    $highRestaurants[] = $restaurant;
                } elseif ($restaurant->orders_count < $targetRemainingPerRestaurant - $tolerance) {
                    $lowRestaurants[] = $restaurant;
                }
            }

            // If all restaurants are balanced, we're done
            if (empty($highRestaurants) || empty($lowRestaurants)) {
                break;
            }

            // Sort to handle most urgent imbalances first
            usort($highRestaurants, function($a, $b) use ($restaurantCounts, $targetRemainingPerRestaurant) {
                return ($restaurantCounts[$b->id] - $targetRemainingPerRestaurant) -
                       ($restaurantCounts[$a->id] - $targetRemainingPerRestaurant);
            });

            usort($lowRestaurants, function($a, $b) use ($restaurantCounts, $targetRemainingPerRestaurant) {
                return ($restaurantCounts[$a->id] - $targetRemainingPerRestaurant) -
                       ($restaurantCounts[$b->id] - $targetRemainingPerRestaurant);
            });

            // Try to balance by moving drivers from high to low restaurants
            foreach ($lowRestaurants as $lowRestaurant) {
                foreach ($highRestaurants as $highRestaurant) {
                    // Find the best driver to reassign
                    $bestDriver = null;
                    $bestDriverScore = PHP_FLOAT_MAX;

                    foreach ($this->drivers as $driver) {
                        if ($driver->next_restaurant_id == $highRestaurant->id && $driver->orders_assigned > 0) {
                            $distanceToLow = $distances[$driver->id][$lowRestaurant->id];

                            // Score based on distance and potential balance improvement
                            $distanceScore = $distanceToLow;
                            $balanceScore = ($restaurantCounts[$highRestaurant->id] - $targetRemainingPerRestaurant) -
                                          ($restaurantCounts[$lowRestaurant->id] - $targetRemainingPerRestaurant);

                            $score = $distanceScore - ($balanceScore * 0.2);

                            if ($score < $bestDriverScore) {
                                $bestDriverScore = $score;
                                $bestDriver = $driver;
                            }
                        }
                    }

                    // Reassign the best driver if it makes sense
                    if ($bestDriver !== null && $bestDriverScore < 8) { // Reasonable distance
                        // Calculate how many orders to move
                        $excessAtHigh = $restaurantCounts[$highRestaurant->id] - $targetRemainingPerRestaurant;
                        $deficitAtLow = $targetRemainingPerRestaurant - $restaurantCounts[$lowRestaurant->id];
                        $ordersToMove = min($bestDriver->orders_assigned, $excessAtHigh, $deficitAtLow);

                        if ($ordersToMove > 0) {
                            // Update counts
                            $restaurantCounts[$highRestaurant->id] -= $ordersToMove;
                            $restaurantCounts[$lowRestaurant->id] += $ordersToMove;

                            // Update driver assignment
                            $bestDriver->next_restaurant_id = $lowRestaurant->id;
                            $bestDriver->orders_assigned = $ordersToMove;

                            // Update actual restaurant objects
                            $highRestaurant->orders_count = $restaurantCounts[$highRestaurant->id];
                            $lowRestaurant->orders_count = $restaurantCounts[$lowRestaurant->id];

                            return; // Move to next iteration
                        }
                    }
                }
            }
        }

        // Final adjustment: ensure no restaurant is below minimum
        foreach ($this->restaurants as $restaurant) {
            if ($restaurant->orders_count < $targetRemainingPerRestaurant) {
                // Find restaurants with excess to donate
                foreach ($this->restaurants as $donor) {
                    if ($donor->orders_count > $targetRemainingPerRestaurant) {
                        $excess = $donor->orders_count - $targetRemainingPerRestaurant;
                        $needed = $targetRemainingPerRestaurant - $restaurant->orders_count;
                        $transfer = min($excess, $needed);

                        if ($transfer > 0) {
                            $donor->orders_count -= $transfer;
                            $restaurant->orders_count += $transfer;
                            break;
                        }
                    }
                }
            }
        }
    }



    public function getReport()
    {
        $report = [
            'restaurants_before' => [],
            'restaurants_after' => [],
            'drivers' => [],
            'stats' => []
        ];

        // Store original orders count for before/after comparison
        $originalOrders = [];
        foreach ($this->restaurants as $restaurant) {
            $originalOrders[$restaurant->id] = $restaurant->orders_count;
        }

        // Randomize to get "before" state
        $this->randomize();

        // Store before state
        foreach ($this->restaurants as $restaurant) {
            $report['restaurants_before'][] = [
                'id' => $restaurant->id,
                'title' => $restaurant->title,
                'lat' => $restaurant->lat,
                'lng' => $restaurant->lng,
                'orders_count' => $restaurant->orders_count
            ];
        }

        // Solve to get assignments
        $this->solve();

        // Validate and fix any capacity violations
        $this->validateDriverCapacities();

        // Store after state and driver assignments
        foreach ($this->restaurants as $restaurant) {
            $report['restaurants_after'][] = [
                'id' => $restaurant->id,
                'title' => $restaurant->title,
                'lat' => $restaurant->lat,
                'lng' => $restaurant->lng,
                'orders_count' => $restaurant->orders_count
            ];
        }

        // Calculate driver stats
        $totalDistance = 0;
        foreach ($this->drivers as $driver) {
            if ($driver->next_restaurant_id > 0) {
                $assignedRestaurant = null;
                foreach ($this->restaurants as $restaurant) {
                    if ($restaurant->id == $driver->next_restaurant_id) {
                        $assignedRestaurant = $restaurant;
                        break;
                    }
                }

                if ($assignedRestaurant) {
                    $distance = haversineDistance(
                        $driver->lat, $driver->lng,
                        $assignedRestaurant->lat, $assignedRestaurant->lng
                    );
                    $totalDistance += $distance;

                    // Find closest restaurant for comparison
                    $closest = null;
                    $minDist = PHP_FLOAT_MAX;
                    foreach ($this->restaurants as $restaurant) {
                        $dist = haversineDistance($driver->lat, $driver->lng, $restaurant->lat, $restaurant->lng);
                        if ($dist < $minDist) {
                            $minDist = $dist;
                            $closest = $restaurant;
                        }
                    }

                    $report['drivers'][] = [
                        'id' => $driver->id,
                        'name' => $driver->name,
                        'position' => ['lat' => $driver->lat, 'lng' => $driver->lng],
                        'assigned_restaurant_id' => $driver->next_restaurant_id,
                        'assigned_restaurant_title' => $assignedRestaurant->title,
                        'distance_to_assigned' => $distance,
                        'closest_restaurant_id' => $closest->id,
                        'closest_restaurant_title' => $closest->title,
                        'distance_to_closest' => $minDist,
                        'orders_assigned' => $driver->orders_assigned,
                        'capacity' => $driver->capacity
                    ];
                }
            } else {
                $closest = null;
                $minDist = PHP_FLOAT_MAX;
                foreach ($this->restaurants as $restaurant) {
                    $dist = haversineDistance($driver->lat, $driver->lng, $restaurant->lat, $restaurant->lng);
                    if ($dist < $minDist) {
                        $minDist = $dist;
                        $closest = $restaurant;
                    }
                }

                $report['drivers'][] = [
                    'id' => $driver->id,
                    'name' => $driver->name,
                    'position' => ['lat' => $driver->lat, 'lng' => $driver->lng],
                    'assigned_restaurant_id' => 0,
                    'assigned_restaurant_title' => 'Unassigned',
                    'distance_to_assigned' => 0,
                    'closest_restaurant_id' => $closest->id,
                    'closest_restaurant_title' => $closest->title,
                    'distance_to_closest' => $minDist,
                    'orders_assigned' => 0,
                    'capacity' => $driver->capacity
                ];
            }
        }

        // Calculate enhanced statistics
        $assignedDriverCount = count($report['drivers']);
        $averageDistance = $assignedDriverCount > 0 ? $totalDistance / $assignedDriverCount : 0;

        // Calculate additional statistics
        $totalAssignedOrders = array_sum(array_map(function($driver) {
            return $driver['orders_assigned'];
        }, $report['drivers']));

        $totalRemainingOrders = array_sum(array_map(function($restaurant) {
            return $restaurant['orders_count'];
        }, $report['restaurants_after']));

        // Calculate total capacity for utilization rate
        $totalCapacity = array_sum(array_map(function($driver) {
            return $driver['capacity'];
        }, $report['drivers']));

        $report['stats'] = [
            'total_drivers_assigned' => $assignedDriverCount,
            'average_distance' => $averageDistance,
            'total_distance' => $totalDistance,
            'total_orders_assigned' => $totalAssignedOrders,
            'total_orders_remaining' => $totalRemainingOrders,
            'utilization_rate' => $totalCapacity > 0 ? ($totalAssignedOrders / $totalCapacity) * 100 : 0
        ];

        return $report;
    }

    protected function validateDriverCapacities()
    {
        foreach ($this->drivers as $driver) {
            // Ensure no driver has more orders than their capacity
            if ($driver->orders_assigned > $driver->capacity) {
                // Find the assigned restaurant
                $assignedRestaurant = null;
                foreach ($this->restaurants as $restaurant) {
                    if ($restaurant->id == $driver->next_restaurant_id) {
                        $assignedRestaurant = $restaurant;
                        break;
                    }
                }

                if ($assignedRestaurant) {
                    // Reduce the driver's orders to their capacity
                    $excessOrders = $driver->orders_assigned - $driver->capacity;
                    $driver->orders_assigned = $driver->capacity;

                    // Return excess orders to the restaurant
                    $assignedRestaurant->orders_count += $excessOrders;
                }
            }
        }
    }

    public function getRestaurants()
    {
        return $this->restaurants;
    }

    public function getDrivers()
    {
        return $this->drivers;
    }
}
