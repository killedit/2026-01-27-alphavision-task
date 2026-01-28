# Database Integration Guide

## Current Issue: Orders Not Stored in Database

The current `RestaurantSystem` class works entirely with in-memory objects and does NOT persist order assignments to the database. Here's why:

### 1. Current Implementation Problems

1. **In-Memory Only**: All order data exists only in PHP objects during runtime
2. **No Database Operations**: The system doesn't create, update, or read from database tables
3. **Temporary State**: Each simulation run starts fresh with no historical data
4. **Missing Relationships**: No tracking of which specific orders go to which drivers

### 2. What Should Be Happening

The system should:
1. **Create Individual Orders**: Instead of `orders_count`, create actual Order records
2. **Track Assignments**: Use the `driver_order` pivot table to record assignments
3. **Persist State**: Save the final assignment state to the database
4. **Retrieve History**: Load previous assignments when needed

## Database Schema (Already Created)

### Orders Table
```php
Schema::create('orders', function (Blueprint $table) {
    $table->id();
    $table->unsignedBigInteger('restaurant_id');
    $table->string('description')->nullable();
    $table->integer('quantity')->default(1);
    $table->string('status')->default('pending'); // pending, assigned, in_transit, delivered, cancelled
    $table->timestamps();
    
    $table->foreign('restaurant_id')->references('id')->on('restaurants')->onDelete('cascade');
});
```

### Driver_Order Pivot Table
```php
Schema::create('driver_order', function (Blueprint $table) {
    $table->id();
    $table->unsignedBigInteger('driver_id');
    $table->unsignedBigInteger('order_id');
    $table->string('status')->default('assigned'); // assigned, in_transit, delivered, cancelled
    $table->timestamps();
    
    $table->foreign('driver_id')->references('id')->on('drivers')->onDelete('cascade');
    $table->foreign('order_id')->references('id')->on('orders')->onDelete('cascade');
    
    $table->unique(['order_id', 'driver_id']); // Prevent duplicate assignments
});
```

## Required Changes to RestaurantSystem

### 1. Database Integration Methods

```php
// Add these methods to RestaurantSystem class

protected function seedOrdersToDatabase()
{
    // Clear existing orders
    Order::truncate();
    
    // Create orders for each restaurant
    foreach ($this->restaurants as $restaurant) {
        for ($i = 0; $i < $restaurant->orders_count; $i++) {
            Order::create([
                'restaurant_id' => $restaurant->id,
                'description' => 'Order #' . ($i + 1) . ' from ' . $restaurant->title,
                'quantity' => 1,
                'status' => 'pending'
            ]);
        }
    }
}

protected function saveAssignmentsToDatabase()
{
    // Clear existing assignments
    DB::table('driver_order')->truncate();
    
    // Save current assignments
    foreach ($this->drivers as $driver) {
        if ($driver->next_restaurant_id > 0 && $driver->orders_assigned > 0) {
            // Find orders from this restaurant that need to be assigned
            $orders = Order::where('restaurant_id', $driver->next_restaurant_id)
                          ->where('status', 'pending')
                          ->take($driver->orders_assigned)
                          ->get();
            
            foreach ($orders as $order) {
                DB::table('driver_order')->insert([
                    'driver_id' => $driver->id,
                    'order_id' => $order->id,
                    'status' => 'assigned',
                    'created_at' => now(),
                    'updated_at' => now()
                ]);
                
                // Update order status
                $order->update(['status' => 'assigned']);
            }
        }
    }
}

protected function loadFromDatabase()
{
    // Load restaurants
    $this->restaurants = [];
    $dbRestaurants = Restaurant::all();
    foreach ($dbRestaurants as $dbRestaurant) {
        $this->restaurants[] = new Restaurant(
            $dbRestaurant->id,
            $dbRestaurant->title,
            $dbRestaurant->lat,
            $dbRestaurant->lng,
            $dbRestaurant->orders_count
        );
    }
    
    // Load drivers
    $this->drivers = [];
    $dbDrivers = Driver::all();
    foreach ($dbDrivers as $dbDriver) {
        $driver = new Driver(
            $dbDriver->id,
            $dbDriver->name,
            $dbDriver->lat,
            $dbDriver->lng,
            $dbDriver->capacity,
            $dbDriver->next_restaurant_id
        );
        
        // Count assigned orders for this driver
        $assignedOrders = DB::table('driver_order')
            ->where('driver_id', $driver->id)
            ->count();
        
        $driver->orders_assigned = $assignedOrders;
        $this->drivers[] = $driver;
    }
    
    // Update restaurant order counts based on database
    foreach ($this->restaurants as $restaurant) {
        $pendingOrders = Order::where('restaurant_id', $restaurant->id)
                            ->where('status', 'pending')
                            ->count();
        $restaurant->orders_count = $pendingOrders;
    }
}
```

### 2. Modified getReport Method

```php
public function getReport()
{
    $report = [
        'restaurants_before' => [],
        'restaurants_after' => [],
        'drivers' => [],
        'stats' => []
    ];

    // Load current state from database
    $this->loadFromDatabase();

    // Store original orders count for before/after comparison
    $originalOrders = [];
    foreach ($this->restaurants as $restaurant) {
        $originalOrders[$restaurant->id] = $restaurant->orders_count;
    }

    // Randomize to get "before" state
    $this->randomize();
    
    // Seed orders to database
    $this->seedOrdersToDatabase();

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
    
    // Save assignments to database
    $this->saveAssignmentsToDatabase();

    // Reload from database to get accurate counts
    $this->loadFromDatabase();

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

    // Calculate driver stats with actual order details
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

                // Get actual assigned order IDs from database
                $assignedOrderIds = DB::table('driver_order')
                    ->where('driver_id', $driver->id)
                    ->pluck('order_id')
                    ->toArray();

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
                    'assigned_order_ids' => $assignedOrderIds // Actual order IDs from database
                ];
            }
        }
    }

    // Calculate statistics
    $report['stats'] = [
        'total_drivers_assigned' => count($report['drivers']),
        'average_distance' => count($report['drivers']) > 0 ? $totalDistance / count($report['drivers']) : 0
    ];

    return $report;
}
```

### 3. Required Imports

Add these at the top of the RestaurantSystem.php file:

```php
use App\Models\Order;
use App\Models\Driver as EloquentDriver;
use App\Models\Restaurant as EloquentRestaurant;
use Illuminate\Support\Facades\DB;
```

## Implementation Steps

### 1. Update RestaurantSystem Class

1. Add the database integration methods
2. Modify the getReport method to use database operations
3. Add the required imports

### 2. Update the Controller

```php
// In RestaurantController.php

public function simulate()
{
    $system = new RestaurantSystem();
    $report = $system->getReport(); // This will now use database
    
    return response()->json($report);
}
```

### 3. Run Migrations

```bash
php artisan migrate
```

### 4. Test the Integration

The system should now:
- Create individual order records in the database
- Track which orders are assigned to which drivers
- Persist this information between requests
- Provide accurate order ID information in reports

## Benefits of Database Integration

1. **Persistent State**: Assignments are saved between page refreshes
2. **Order Tracking**: Each individual order is tracked with its status
3. **Historical Data**: Can analyze past assignments and performance
4. **Accurate Reporting**: Reports show actual order IDs, not just counts
5. **Audit Trail**: Complete history of order status changes
6. **Scalability**: Can handle much larger datasets

## Current Limitations

The current implementation still has these issues:

1. **No Database Connection**: MySQL connection is not working in the current environment
2. **In-Memory Fallback**: The system falls back to in-memory operations when database fails
3. **Missing Seeder**: No database seeder for initial restaurant/driver data
4. **No Order History**: Can't track order status changes over time

## Recommendations

1. **Fix Database Connection**: Resolve the MySQL connection issues
2. **Create Seeders**: Implement proper database seeders for initial data
3. **Add Order History**: Create a table to track order status changes over time
4. **Implement Caching**: Cache frequent database queries for better performance
5. **Add API Endpoints**: Create endpoints for order/driver management

The simplified algorithm now prioritizes proximity (drivers take orders from closest restaurants first) and the database integration plan provides a clear path to persistent order tracking.