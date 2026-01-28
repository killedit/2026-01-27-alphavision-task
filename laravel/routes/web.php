<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\RestaurantController;

Route::get('/', function () {
    return view('welcome');
});

Route::get('/restaurant', [RestaurantController::class, 'index']);
Route::get('/restaurant/simulate', [RestaurantController::class, 'simulate']);
