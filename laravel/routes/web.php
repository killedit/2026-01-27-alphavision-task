<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\RestaurantController;
use App\Http\Controllers\DispatchController;

Route::get('/', function () {
    return view('welcome');
});

// Route::get('/restaurant', [RestaurantController::class, 'index']);
Route::get('/restaurant', [RestaurantController::class, 'index']);
Route::get('/restaurant/simulate', [RestaurantController::class, 'simulate']);

