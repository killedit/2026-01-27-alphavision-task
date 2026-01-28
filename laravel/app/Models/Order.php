<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Order extends Model
{
    use HasFactory;

    protected $fillable = [
        'restaurant_id',
        'description',
        'quantity',
        'status'
    ];

    public function restaurant()
    {
        return $this->belongsTo(Restaurant::class);
    }

    public function drivers()
    {
        return $this->belongsToMany(Driver::class, 'driver_order')
                    ->withPivot('status')
                    ->withTimestamps();
    }

    public function currentDriver()
    {
        return $this->belongsToMany(Driver::class, 'driver_order')
                    ->wherePivot('status', 'assigned')
                    ->latest();
    }
}