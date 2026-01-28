<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\RestaurantSystem\RestaurantSystem;

class RestaurantController extends Controller
{
    public function index()
    {
        $system = new RestaurantSystem();
        $report = $system->getReport();
        
        return view('restaurant.index', [
            'report' => $report
        ]);
    }
    
    public function simulate()
    {
        $system = new RestaurantSystem();
        $report = $system->getReport();
        
        return response()->json($report);
    }
}