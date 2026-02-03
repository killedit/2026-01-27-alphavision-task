<?php

namespace App\Http\Controllers;

use App\Services\RestaurantService;
use Illuminate\Http\JsonResponse;

class DispatchController extends Controller
{
    public function index(RestaurantService $service): JsonResponse
    {
        $results = $service->solve();

        return response()->json([
            'status' => 'success',
            'timestamp' => now()->toDateTimeString(),
            'assignments' => $results
        ]);
    }
}
