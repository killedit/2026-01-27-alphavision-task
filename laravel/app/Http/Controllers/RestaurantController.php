<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\RestaurantSystem\RestaurantSystem;
use App\Services\RestaurantService;

class RestaurantController extends Controller
{
    public function index(RestaurantService $service)
    {
        $report = $service->generateFullReport();

        return view('restaurant.index', compact('report'));
    }

    public function simulate(RestaurantService $service)
    {
        $service->randomize();

        $report = $service->generateFullReport();

// dd(
//     $report,
// );

        return response()->json($report);
    }
}
