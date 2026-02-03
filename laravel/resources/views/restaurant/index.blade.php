<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>AlphaVision Task</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
    <link rel="stylesheet" href="{{ asset('css/app.css') }}">
</head>

<body>
    <div class="container-fluid py-3">
        <div class="card mb-3 shadow-sm">
            <div class="card-body d-flex justify-content-between align-items-center">
                <h2 class="mb-0">Simulation Statistics:</h2>
                <button id="simulateBtn" class="btn btn-primary btn-lg">Run New Simulation</button>
            </div>
        </div>

        <div class="row g-3 mb-3 text-center">
            @foreach ([
        'Drivers Assigned' => ['id' => 'totalDrivers', 'val' => $report['stats']['total_drivers_assigned'], 'unit' => ''],
        'Avg Distance' => ['id' => 'avgDistance', 'val' => number_format($report['stats']['average_distance'], 2), 'unit' => 'km'],
        'Orders Picked' => ['id' => 'totalOrdersAssigned', 'val' => $report['stats']['total_orders_assigned'], 'unit' => ''],
        'Utilization' => ['id' => 'utilizationRate', 'val' => number_format($report['stats']['utilization_rate'], 1), 'unit' => '%'],
    ] as $label => $stat)
                <div class="col-md-3">
                    <div class="card shadow-sm border-0 bg-light">
                        <div class="card-body p-2">
                            <small class="text-muted d-block">{{ $label }}</small>
                            <span id="{{ $stat['id'] }}" class="h4 mb-0">{{ $stat['val'] }}</span>
                            {{ $stat['unit'] }}
                        </div>
                    </div>
                </div>
            @endforeach
        </div>

        <div class="row g-3">
            <div class="col-md-6">
                <div class="card shadow-sm h-100">
                    <div class="card-header bg-white font-weight-bold">Logistics Map</div>
                    <div class="card-body p-0">
                        <div id="map" style="height: 100%; width: 100%;"></div>
                    </div>
                </div>
            </div>

            <div class="col-md-6">
                <div class="card shadow-sm mb-3">
                    <div class="card-header bg-white">Restaurant Orders</div>
                    <div class="table-responsive" style="max-height: 330px;">
                        <table class="table table-hover mb-0" id="resTable">
                            <thead class="table-light sticky-top">
                                <tr>
                                    <th class="sortable" data-type="num">ID</th>
                                    <th class="sortable" data-type="string">Name</th>
                                    <th class="sortable" data-type="num">Before</th>
                                    <th class="sortable" data-type="num">After</th>
                                    <th>Drivers</th>
                                </tr>
                            </thead>
                            <tbody id="restaurantsComparison">
                                @php
                                    $colors = [
                                        '#FF5733',
                                        '#480607',//Bulgarian Rose
                                        '#3357FF',
                                        '#2f4f4f',//Dark Slate Grey
                                        '#FF33F3',
                                        '#008080',//Teal
                                        '#8A2BE2',
                                        '#e30b5d',//Raspberry
                                        '#e2725b',//Terra Cotta
                                        '#da9100'//Harvest Gold
                                    ];
                                    $driverColl = collect($report['drivers']);
                                @endphp
                                @foreach ($report['restaurants_before'] as $index => $resBefore)
                                    @php
                                        $resAfter = $report['restaurants_after'][$index];
                                        $rowColor = $colors[$index % count($colors)];
                                        $assigned = $driverColl
                                            ->where('assigned_restaurant_id', $resBefore['id'])
                                            ->pluck('id')
                                            ->implode(', ');
                                    @endphp
                                    <tr style="background-color: {{ $rowColor }}; color: white;">
                                        <td>{{ $resBefore['id'] }}</td>
                                        <td>{{ $resBefore['title'] }}</td>
                                        <td>{{ $resBefore['orders_count'] }}</td>
                                        <td>{{ $resAfter['orders_count'] }}</td>
                                        <td>{{ $assigned ?: '—' }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>

                <div class="card shadow-sm">
                    <div class="card-header bg-white">Driver Assignments</div>
                    <div class="table-responsive" style="max-height: 330px;">
                        <table class="table table-sm table-striped mb-0" id="driverTable">
                            <thead class="table-light sticky-top">
                                <tr>
                                    <th class="sortable" data-type="num">ID</th>
                                    <th class="sortable" data-type="num">Orders</th>
                                    <th class="sortable" data-type="num">Restaurant ID</th>
                                    <th class="sortable" data-type="string">Restaurant</th>
                                    <th class="sortable" data-type="num">Dist</th>
                                    <th class="sortable" data-type="string">Status</th>
                                </tr>
                            </thead>
                            <tbody id="driversTable">
                                @foreach ($report['drivers'] as $driver)
                                    <tr>
                                        <td>{{ $driver['id'] }}</td>
                                        <td>{{ $driver['orders_assigned'] }}</td>
                                        <td>{{ $driver['assigned_restaurant_id'] }}</td>
                                        <td>{{ $driver['assigned_restaurant_title'] }}</td>
                                        <td>{{ number_format($driver['distance_to_assigned'], 2) }}</td>
                                        <td>
                                            <span
                                                class="badge {{ $driver['assigned_restaurant_id'] > 0 ? 'bg-success' : 'bg-secondary' }}">
                                                {{ $driver['assigned_restaurant_id'] > 0 ? 'Assigned' : 'Idle' }}
                                            </span>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        window.initialReport = @json($report);
    </script>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
    <script src="{{ asset('js/app.js') }}"></script>
</body>

</html>
