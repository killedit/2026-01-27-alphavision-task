<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Driver extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'lat',
        'lng',
        'capacity',
        'next_restaurant_id'
    ];

    public function orders()
    {
        return $this->belongsToMany(Order::class, 'driver_order')
                    ->withPivot('status')
                    ->withTimestamps();
    }

    public function currentOrders()
    {
        return $this->belongsToMany(Order::class, 'driver_order')
                    ->wherePivot('status', 'assigned');
    }

    public function restaurant()
    {
        return $this->belongsTo(Restaurant::class, 'next_restaurant_id');
    }
}