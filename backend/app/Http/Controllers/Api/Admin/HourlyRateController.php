<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreHourlyRatesRequest;
use App\Services\HourlyRateService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class HourlyRateController extends Controller
{
    public function __construct(private readonly HourlyRateService $rates)
    {
    }

    /**
     * Tarif yang berlaku hari ini per slot jam operasional.
     */
    public function index(): JsonResponse
    {
        return response()->json(['data' => $this->rates->current()]);
    }

    public function history(): JsonResponse
    {
        return response()->json(['data' => $this->rates->versions()]);
    }

    public function store(StoreHourlyRatesRequest $request): JsonResponse
    {
        $this->rates->saveVersion($request->validated('effective_date'), $request->validated('rates'), $request->user());

        return response()->json([
            'message' => 'Tarif berhasil disimpan.',
            'data' => $this->rates->current(),
        ], 201);
    }

    public function destroy(Request $request, string $effectiveDate): JsonResponse
    {
        $this->rates->deleteVersion($effectiveDate);

        return response()->json(['message' => 'Versi tarif berhasil dihapus.']);
    }
}
