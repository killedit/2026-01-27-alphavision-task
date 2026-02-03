<?php

// Geographic utility functions for the restaurant logistics system

/**
 * Calculate distance between two points using Haversine formula
 * Returns distance in kilometers
 */
function haversineDistance($lat1, $lng1, $lat2, $lng2) {
    $earthRadius = 6371; // Earth radius in km
    
    $dLat = deg2rad($lat2 - $lat1);
    $dLng = deg2rad($lng2 - $lng1);
    
    $a = sin($dLat/2) * sin($dLat/2) +
         cos(deg2rad($lat1)) * cos(deg2rad($lat2)) *
         sin($dLng/2) * sin($dLng/2);
    
    $c = 2 * atan2(sqrt($a), sqrt(1-$a));
    
    return $earthRadius * $c;
}

/**
 * Generate random point near given coordinates within max distance
 * Returns array with 'lat' and 'lng'
 */
function randomPointNear($lat, $lng, $maxKm = 5) {
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

/**
 * Find closest restaurant to a given point
 */
function findClosestRestaurant($lat, $lng, $restaurants) {
    $closest = null;
    $minDistance = PHP_FLOAT_MAX;
    
    foreach ($restaurants as $restaurant) {
        $distance = haversineDistance($lat, $lng, $restaurant['lat'], $restaurant['lng']);
        if ($distance < $minDistance) {
            $minDistance = $distance;
            $closest = $restaurant;
        }
    }
    
    return ['restaurant' => $closest, 'distance' => $minDistance];
}