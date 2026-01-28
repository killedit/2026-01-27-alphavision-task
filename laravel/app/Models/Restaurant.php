<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Restaurant extends Model
{
    use HasFactory;

    protected $fillable = [
        'title',
        'lat',
        'lng',
        'orders_count'
    ];

    public function orders()
    {
        return $this->hasMany(Order::class);
    }

    public function drivers()
    {
        return $this->hasManyThrough(Driver::class, Order::class, 'restaurant_id', 'next_restaurant_id', 'id', 'id');
    }

    public function pendingOrders()
    {
        return $this->hasMany(Order::class)->where('status', 'pending');
    }
}