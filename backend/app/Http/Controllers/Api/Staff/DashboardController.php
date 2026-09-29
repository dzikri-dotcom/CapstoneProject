<?php

namespace App\Http\Controllers\Api\Staff;

use App\Http\Controllers\Controller;
use App\Services\Reports\DashboardService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    /**
     * Shift berikutnya, jam kerja, dan estimasi gaji bulan berjalan.
     */
    public function __invoke(Request $request, DashboardService $dashboard): JsonResponse
    {
        return response()->json(['data' => $dashboard->staff($request->user()->staffProfile)]);
    }
}
