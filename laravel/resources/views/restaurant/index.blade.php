<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Restaurant Logistics System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
    <style>
        body {
            padding: 20px;
        }
        .restaurant-card {
            margin-bottom: 15px;
        }
        .driver-row {
            font-size: 0.9em;
        }
        .stats-box {
            background-color: #f8f9fa;
            padding: 15px;
            border-radius: 5px;
            margin-bottom: 20px;
        }
        .btn-simulate {
            margin-bottom: 20px;
        }
        #map {
            height: 500px;
            width: 100%;
            margin-bottom: 20px;
            border: 1px solid #ddd;
            border-radius: 5px;
        }
        .map-container {
            margin-bottom: 20px;
        }
        .legend {
            background: white;
            padding: 10px;
            border-radius: 5px;
            box-shadow: 0 0 10px rgba(0,0,0,0.1);
        }
        .restaurant-marker {
            cursor: pointer;
        }
        .restaurant-marker div {
            user-select: none;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1 class="text-center mb-4">Restaurant Logistics System</h1>

        <div class="btn-simulate text-center">
            <button id="simulateBtn" class="btn btn-primary btn-lg">Run New Simulation</button>
        </div>

        <div class="stats-box">
            <h3>Simulation Statistics</h3>
            <div class="row">
                <div class="col-md-6">
                    <div class="row">
                        <div class="col-md-6">
                            <p><strong>Total Drivers Assigned:</strong> <span id="totalDrivers">{{ $report['stats']['total_drivers_assigned'] ?? 0 }}</span></p>
                            <p><strong>Average Distance:</strong> <span id="avgDistance">{{ number_format($report['stats']['average_distance'] ?? 0, 2) }}</span> km</p>
                            <p><strong>Total Distance:</strong> <span id="totalDistance">{{ number_format($report['stats']['total_distance'] ?? 0, 2) }}</span> km</p>
                        </div>
                        <div class="col-md-6">
                            <p><strong>Total Orders Assigned:</strong> <span id="totalOrdersAssigned">{{ $report['stats']['total_orders_assigned'] ?? 0 }}</span></p>
                            <p><strong>Total Orders Remaining:</strong> <span id="totalOrdersRemaining">{{ $report['stats']['total_orders_remaining'] ?? 0 }}</span></p>
                            <p><strong>Utilization Rate:</strong> <span id="utilizationRate">{{ number_format($report['stats']['utilization_rate'] ?? 0, 1) }}</span>%</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="map-container">
            <h3>Geographic Visualization</h3>
            <div id="map"></div>
            <div class="legend mt-2">
                <h5>Legend</h5>
                <p><span style="background-color: #FF5733; color: white; padding: 2px 6px; border: 1px solid #000; border-radius: 2px; font-size: 12px; font-weight: bold;">1</span> Restaurants (color indicates restaurant)</p>
                <p>
  <span style="background-color: blue; color: white; padding: 2px 6px; border: 1px solid #000; border-radius: 50%; font-size: 12px; font-weight: bold;">1</span>
  Drivers (color indicates assigned restaurant)
</p>
            </div>
        </div>

        <div class="mt-4">
            <h3>Restaurant Orders</h3>
            <div class="table-responsive">
                <table class="table table-striped table-bordered">
                    <thead class="">
                        <tr>
                            <th>Restaurant ID</th>
                            <th>Name</th>
                            <th>Orders Before</th>
                            <th>Orders After</th>
                            <th>Assigned Drivers</th>
                        </tr>
                    </thead>
                    <tbody id="restaurantsComparison">
                        @if(isset($report['restaurants_before']) && isset($report['restaurants_after']) && is_array($report['restaurants_before']) && is_array($report['restaurants_after']))
                            @foreach ($report['restaurants_before'] as $index => $restaurantBefore)
                                @php
                                    $restaurantAfter = $report['restaurants_after'][$index] ?? ['orders_count' => 0];

                                    // Find drivers assigned to this restaurant
                                    $assignedDriverIds = [];
                                    if (isset($report['drivers']) && is_array($report['drivers'])) {
                                        foreach ($report['drivers'] as $driver) {
                                            if (($driver['assigned_restaurant_id'] ?? 0) == ($restaurantBefore['id'] ?? 0)) {
                                                $assignedDriverIds[] = $driver['id'];
                                            }
                                        }
                                    }
                                @endphp
                                <tr>
                                    <td>{{ $index + 1 }}</td>
                                    <td>{{ $restaurantBefore['title'] ?? 'Unknown' }}</td>
                                    <td>{{ $restaurantBefore['orders_count'] ?? 0 }}</td>
                                    <td>{{ $restaurantAfter['orders_count'] ?? 0 }}</td>
                                    <td>{{ implode(', ', $assignedDriverIds) }}</td>
                                </tr>
                            @endforeach
                        @else
                            <tr>
                                <td colspan="5" class="text-center">No restaurant data available</td>
                            </tr>
                        @endif
                    </tbody>
                </table>
            </div>
        </div>

        <div class="mt-4">
            <h3>Driver Assignments (All 100 Drivers)</h3>
            <div class="table-responsive" style="max-height: 600px; overflow-y: auto;">
                <table class="table table-striped table-bordered">
                    <thead style="">
                        <tr>
                            <th>Driver ID</th>
                            <th>Orders</th>
                            <th>Restaurant ID</th>
                            <th>Restaurant Name (Assigned)</th>
                            <th>Distance</th>
                            <th>Closest Restaurant</th>
                            <th>Closest Dist</th>
                        </tr>
                    </thead>
                    <tbody id="driversTable">
                        @if(isset($report['drivers']) && is_array($report['drivers']))
                            @foreach ($report['drivers'] as $driver)
                                @php
                                    // Find orders from restaurants for this driver
                                    $ordersFromRestaurants = [];
                                    if (isset($report['restaurants_before']) && is_array($report['restaurants_before'])) {
                                        foreach ($report['restaurants_before'] as $restaurant) {
                                            if (($restaurant['id'] ?? 0) == ($driver['assigned_restaurant_id'] ?? 0)) {
                                                $ordersFromRestaurants[] = $restaurant['id'];
                                            }
                                        }
                                    }
                                @endphp
                            <tr class="driver-row">
                                <td>{{ $driver['id'] ?? 'N/A' }}</td>
                                <td>{{ $driver['orders_assigned'] ?? 0 }}</td>
                                <td>{{ implode(', ', $ordersFromRestaurants) }}</td>
                                <td>{{ $driver['assigned_restaurant_title'] ?? 'N/A' }}</td>
                                <td>{{ isset($driver['distance_to_assigned']) ? number_format($driver['distance_to_assigned'], 2) : '0.00' }} km</td>
                                <td>{{ $driver['closest_restaurant_title'] ?? 'N/A' }}</td>
                                <td>{{ isset($driver['distance_to_closest']) ? number_format($driver['distance_to_closest'], 2) : '0.00' }} km</td>
                            </tr>
                            @endforeach
                        @else
                            <tr>
                                <td colspan="7" class="text-center">No driver data available</td>
                            </tr>
                        @endif
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
    <script>
        // Global map variable
        let map;
        let restaurantMarkers = [];
        let driverMarkers = [];
        let driversLines = [];

        // Fixed color palette for restaurants (consistent index-based)
        const restaurantColors = [
            '#FF5733', '#33FF57', '#3357FF', '#F3FF33', '#FF33F3',
            '#33FFF3', '#8A2BE2', '#FF6347', '#7CFC00', '#FFD700'
        ];

        // Initialize map
        function initMap() {
            try {
                map = L.map('map').setView([42.6977, 23.3219], 12); // Sofia center

                L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                    attribution: '© <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors'
                }).addTo(map);

                // Initialize with current data
                const initialData = @json($report);
                updateMap(initialData);
            } catch (error) {
                console.error('Error initializing map:', error);
                $('#map').html('<div class="alert alert-danger">Error loading map. Please refresh the page.</div>');
            }
        }

        // Update map with new data
        function updateMap(data) {
            // Clear existing markers and lines
            if (restaurantMarkers.length > 0) {
                restaurantMarkers.forEach(marker => map.removeLayer(marker));
            }
            if (driverMarkers.length > 0) {
                driverMarkers.forEach(marker => map.removeLayer(marker));
            }
            if (driversLines.length > 0) {
                driversLines.forEach(line => map.removeLayer(line));
            }
            restaurantMarkers = [];
            driverMarkers = [];
            driversLines = [];

            // Add restaurants as compact numbered markers
            if (data.restaurants_after && data.restaurants_after.length > 0) {
                data.restaurants_after.forEach((restaurant, index) => {
                    const color = restaurantColors[index % restaurantColors.length];

                    // Create a compact square marker with the restaurant number
                    const restaurantIcon = L.divIcon({
                        html: `<div style="
                            width: 24px;
                            height: 24px;
                            background-color: ${color};
                            border: 1px solid #000;
                            border-radius: 2px;
                            display: flex;
                            align-items: center;
                            justify-content: center;
                            font-size: 12px;
                            font-weight: bold;
                            color: white;
                            box-shadow: 1px 1px 2px rgba(0,0,0,0.3);
                        ">${index + 1}</div>`,
                        iconSize: [24, 24],
                        iconAnchor: [12, 12],
                        className: 'restaurant-marker'
                    });

                    const marker = L.marker([parseFloat(restaurant.lat), parseFloat(restaurant.lng)], {
                        icon: restaurantIcon,
                        zIndexOffset: 1000
                    }).addTo(map);

                    marker.bindPopup(`<b>${restaurant.title}</b><br>Orders: ${restaurant.orders_count}`);
                    restaurantMarkers.push(marker);

                    // Store color and index for this restaurant
                    restaurant.color = color;
                    restaurant.colorIndex = index;
                    restaurant.number = index + 1;
                });
            }

            // Add drivers as circles with numbers and draw lines to restaurants
            if (data.drivers && data.drivers.length > 0) {
                data.drivers.forEach(driver => {
                    // Find the assigned restaurant
                    const assignedRestaurant = data.restaurants_after.find(r => r.id === driver.assigned_restaurant_id);
                    if (assignedRestaurant) {
                        const color = assignedRestaurant.color || restaurantColors[assignedRestaurant.colorIndex % restaurantColors.length];

                        // Draw line from restaurant to driver (solid line)
                        const line = L.polyline([
                            [parseFloat(assignedRestaurant.lat), parseFloat(assignedRestaurant.lng)],
                            [parseFloat(driver.position.lat), parseFloat(driver.position.lng)]
                        ], {
                            color: color,
                            weight: 2,
                            opacity: 0.7
                        }).addTo(map);
                        driversLines.push(line);

                        // Create driver marker with number label
                        const marker = L.circleMarker([parseFloat(driver.position.lat), parseFloat(driver.position.lng)], {
                            radius: 7,
                            fillColor: color,
                            color: '#000',
                            weight: 1,
                            opacity: 1,
                            fillOpacity: 0.8
                        }).addTo(map);

                        // Add text label with driver number
                        const label = L.divIcon({
                            html: `<div style="font-size: 10px; font-weight: bold; color: white; text-align: center; line-height: 14px;">${driver.id}</div>`,
                            iconSize: [14, 14],
                            className: 'driver-label'
                        });

                        const textMarker = L.marker([parseFloat(driver.position.lat), parseFloat(driver.position.lng)], {
                            icon: label
                        }).addTo(map);
                        driverMarkers.push(textMarker);

                        marker.bindPopup(`<b>Driver ${driver.id}</b><br>
                                        Orders: ${driver.orders_assigned || 0}<br>
                                        Assigned to: ${assignedRestaurant.title}`);
                    }
                });
            }
        }

        $(document).ready(function() {
            // Initialize map when DOM is ready and map container is visible
            if ($('#map').length > 0) {
                // Small delay to ensure container is properly rendered
                setTimeout(function() {
                    initMap();
                }, 100);
            } else {
                console.error('Map container not found');
            }

            $('#simulateBtn').click(function() {
                $(this).prop('disabled', true).text('Simulating...');

                $.ajax({
                    url: '/restaurant/simulate',
                    method: 'GET',
                    success: function(response) {
                        // Update statistics
                        $('#totalDrivers').text(response.stats.total_drivers_assigned);
                        $('#avgDistance').text(parseFloat(response.stats.average_distance).toFixed(2));
                        $('#totalDistance').text(parseFloat(response.stats.total_distance).toFixed(2));
                        $('#totalOrdersAssigned').text(response.stats.total_orders_assigned);
                        $('#totalOrdersRemaining').text(response.stats.total_orders_remaining);
                        $('#utilizationRate').text(parseFloat(response.stats.utilization_rate).toFixed(1));

                        // Update restaurants before
                        let beforeHtml = '';
                        response.restaurants_before.forEach(function(restaurant) {
                            beforeHtml += `
                                <div class="card restaurant-card">
                                    <div class="card-body">
                                        <h5 class="card-title">${restaurant.title}</h5>
                                        <p class="card-text">Orders: ${restaurant.orders_count}</p>
                                    </div>
                                </div>
                            `;
                        });
                        $('#restaurantsBefore').html(beforeHtml);

                        // Update restaurants after
                        let afterHtml = '';
                        response.restaurants_after.forEach(function(restaurant) {
                            afterHtml += `
                                <div class="card restaurant-card">
                                    <div class="card-body">
                                        <h5 class="card-title">${restaurant.title}</h5>
                                        <p class="card-text">Orders: ${restaurant.orders_count}</p>
                                    </div>
                                </div>
                            `;
                        });
                        $('#restaurantsAfter').html(afterHtml);

                        // Update drivers table - ALL 100 drivers
                        let driversHtml = '';
                        if (response.drivers && response.drivers.length > 0) {
                            response.drivers.forEach(function(driver) {
                                // Find orders from restaurants for this driver
                                let ordersFromRestaurants = [];
                                if (response.restaurants_before && response.restaurants_before.length > 0) {
                                    response.restaurants_before.forEach(function(restaurant) {
                                        if (restaurant.id === driver.assigned_restaurant_id) {
                                            // For now, we'll just show the assigned restaurant ID
                                            // In a full implementation, this would show specific order IDs
                                            ordersFromRestaurants.push(restaurant.id);
                                        }
                                    });
                                }

                                driversHtml += `
                                    <tr class="driver-row">
                                        <td><strong>${driver.id}</strong></td>
                                        <td>${driver.orders_assigned || 0}</td>
                                        <td>${driver.assigned_restaurant_title || 'N/A'}</td>
                                        <td>${driver.distance_to_assigned ? driver.distance_to_assigned.toFixed(2) : '0.00'} km</td>
                                        <td>${driver.closest_restaurant_title || 'N/A'}</td>
                                        <td>${driver.distance_to_closest ? driver.distance_to_closest.toFixed(2) : '0.00'} km</td>
                                        <td>${ordersFromRestaurants.join(', ') || 'N/A'}</td>
                                    </tr>
                                `;
                            });
                        } else {
                            driversHtml = '<tr><td colspan="7" class="text-center">No drivers assigned</td></tr>';
                        }
                        $('#driversTable').html(driversHtml);

                        // Update map
                        updateMap(response);

                        $('#simulateBtn').prop('disabled', false).text('Run New Simulation');
                    },
                    error: function(xhr, status, error) {
                        console.error('Simulation error:', error);
                        alert('Error running simulation: ' + (error || 'Unknown error'));
                        $('#simulateBtn').prop('disabled', false).text('Run New Simulation');
                    }
                });
            });
        });
    </script>
</body>
</html>
