<?php

namespace App\RestaurantSystem;

class Driver
{
    public $id;
    public $name;
    public $lat;
    public $lng;
    public $capacity;
    public $next_restaurant_id;
    public $orders_assigned;

    public function __construct($id, $name, $lat, $lng, $capacity = 1, $next_restaurant_id = 0)
    {
        $this->id = $id;
        $this->name = $name;
        $this->lat = $lat;
        $this->lng = $lng;
        $this->capacity = $capacity;
        $this->next_restaurant_id = $next_restaurant_id;
        $this->orders_assigned = 0;
    }

    public function toArray()
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'lat' => $this->lat,
            'lng' => $this->lng,
            'capacity' => $this->capacity,
            'next_restaurant_id' => $this->next_restaurant_id,
            'orders_assigned' => $this->orders_assigned
        ];
    }
}
