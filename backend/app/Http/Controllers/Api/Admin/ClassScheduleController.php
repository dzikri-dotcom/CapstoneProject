<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\ClassScheduleRequest;
use App\Http\Resources\ClassScheduleResource;
use App\Models\ClassSchedule;
use App\Models\StaffProfile;
use App\Services\Scheduling\AvailabilityService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * Admin melihat & mengedit jadwal kuliah staff mana pun.
 */
class ClassScheduleController extends Controller
{
    public function index(StaffProfile $staffProfile): AnonymousResourceCollection
    {
        return ClassScheduleResource::collection($staffProfile->classSchedules);
    }

    public function store(ClassScheduleRequest $request, StaffProfile $staffProfile): JsonResponse
    {
        $schedule = $staffProfile->classSchedules()->create($request->validated());

        return (new ClassScheduleResource($schedule))
            ->additional(['message' => 'Jadwal kuliah berhasil ditambahkan.'])
            ->response()
            ->setStatusCode(201);
    }

    public function update(ClassScheduleRequest $request, ClassSchedule $classSchedule): ClassScheduleResource
    {
        $classSchedule->update($request->validated());

        return (new ClassScheduleResource($classSchedule))->additional(['message' => 'Jadwal kuliah berhasil diperbarui.']);
    }

    public function destroy(ClassSchedule $classSchedule): JsonResponse
    {
        $classSchedule->delete();

        return response()->json(['message' => 'Jadwal kuliah berhasil dihapus.']);
    }

    public function availability(StaffProfile $staffProfile, AvailabilityService $availability): JsonResponse
    {
        return response()->json(['data' => $availability->forStaff($staffProfile)]);
    }
}
