<?php

namespace App\RestaurantSystem;

class Restaurant
{
    public $id;
    public $title;
    public $lat;
    public $lng;
    public $orders_count;
    
    public function __construct($id, $title, $lat, $lng, $orders_count = 0)
    {
        $this->id = $id;
        $this->title = $title;
        $this->lat = $lat;
        $this->lng = $lng;
        $this->orders_count = $orders_count;
    }
    
    public function toArray()
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'lat' => $this->lat,
            'lng' => $this->lng,
            'orders_count' => $this->orders_count
        ];
    }
}