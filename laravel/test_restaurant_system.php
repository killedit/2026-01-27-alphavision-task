<?php

require_once __DIR__ . '/vendor/autoload.php';

use App\RestaurantSystem\RestaurantSystem;

// Create restaurant system instance
$system = new RestaurantSystem();

// Generate report
$report = $system->getReport();

echo "=== RESTAURANT SYSTEM REPORT ===\n\n";

echo "RESTAURANTS BEFORE SOLVING:\n";
foreach ($report['restaurants_before'] as $restaurant) {
    echo sprintf("%-20s: %d orders\n", $restaurant['title'], $restaurant['orders_count']);
}

echo "\nRESTAURANTS AFTER SOLVING:\n";
foreach ($report['restaurants_after'] as $restaurant) {
    echo sprintf("%-20s: %d orders\n", $restaurant['title'], $restaurant['orders_count']);
}

echo "\nDRIVER ASSIGNMENTS (first 10):\n";
$driverCount = 0;
foreach ($report['drivers'] as $driver) {
    if ($driverCount >= 10) break;
    echo sprintf("Driver %d: Assigned to %s (%.2f km), Closest was %s (%.2f km)\n",
        $driver['id'],
        $driver['assigned_restaurant_title'],
        $driver['distance_to_assigned'],
        $driver['closest_restaurant_title'],
        $driver['distance_to_closest']
    );
    $driverCount++;
}

echo "\nSTATISTICS:\n";
echo sprintf("Total drivers assigned: %d\n", $report['stats']['total_drivers_assigned']);
echo sprintf("Average distance: %.2f km\n", $report['stats']['average_distance']);

echo "\n=== REPORT COMPLETE ===\n";