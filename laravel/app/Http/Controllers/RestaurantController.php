<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\RestaurantSystem\RestaurantSystem;
use App\Services\RestaurantService;

class RestaurantController extends Controller
{
    // public function index()
    // {
    //     $system = new RestaurantSystem();
    //     $report = $system->getReport();

    //     return view('restaurant.index', [
    //         'report' => $report
    //     ]);
    // }

    // public function simulate()
    // {
    //     $system = new RestaurantSystem();
    //     $report = $system->getReport();

    //     return response()->json($report);
    // }

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
