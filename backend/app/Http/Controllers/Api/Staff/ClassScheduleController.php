<?php

namespace App\Http\Controllers\Api\Staff;

use App\Http\Controllers\Controller;
use App\Http\Requests\ClassScheduleRequest;
use App\Http\Resources\ClassScheduleResource;
use App\Models\ClassSchedule;
use App\Services\Scheduling\AvailabilityService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * Jadwal kuliah milik staff yang sedang login.
 */
class ClassScheduleController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        return ClassScheduleResource::collection($request->user()->staffProfile->classSchedules);
    }

    public function store(ClassScheduleRequest $request): JsonResponse
    {
        $schedule = $request->user()->staffProfile->classSchedules()->create($request->validated());

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

    public function destroy(Request $request, ClassSchedule $classSchedule): JsonResponse
    {
        abort_unless($classSchedule->staff_profile_id === $request->user()->staffProfile->id, 403, __('auth.forbidden'));

        $classSchedule->delete();

        return response()->json(['message' => 'Jadwal kuliah berhasil dihapus.']);
    }

    public function availability(Request $request, AvailabilityService $availability): JsonResponse
    {
        return response()->json(['data' => $availability->forStaff($request->user()->staffProfile)]);
    }
}
