<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\UpdateOperationalHoursRequest;
use App\Http\Resources\OperationalHourResource;
use App\Models\OperationalHour;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\DB;

class OperationalHourController extends Controller
{
    public function index(): AnonymousResourceCollection
    {
        return OperationalHourResource::collection(OperationalHour::query()->orderBy('day_of_week')->get());
    }

    public function update(UpdateOperationalHoursRequest $request): AnonymousResourceCollection
    {
        DB::transaction(function () use ($request) {
            foreach ($request->validated('days') as $day) {
                OperationalHour::query()->updateOrCreate(
                    ['day_of_week' => $day['day_of_week']],
                    [
                        'is_active' => $day['is_active'],
                        'open_time' => $day['open_time'],
                        'close_time' => $day['close_time'],
                    ],
                );
            }
        });

        return OperationalHourResource::collection(OperationalHour::query()->orderBy('day_of_week')->get())
            ->additional(['message' => 'Jam operasional berhasil disimpan.']);
    }
}
