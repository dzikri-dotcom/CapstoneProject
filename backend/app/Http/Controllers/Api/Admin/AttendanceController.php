<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\CorrectAttendanceRequest;
use App\Http\Requests\Admin\ListAttendancesRequest;
use App\Http\Resources\ShiftAttendanceResource;
use App\Models\ShiftAssignment;
use App\Services\Attendance\AttendanceService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * Monitoring presensi per tanggal & koreksi manual oleh admin.
 */
class AttendanceController extends Controller
{
    public function __construct(private readonly AttendanceService $attendance)
    {
    }

    public function index(ListAttendancesRequest $request): AnonymousResourceCollection
    {
        // Pastikan shift yang sudah lewat tanpa check-in tercatat tidak hadir walau scheduler belum jalan.
        $this->attendance->markAbsent();

        $date = $request->validated('date') ?? today()->toDateString();
        $now = now();

        $assignments = $this->attendance->publishedAssignments()
            ->whereHas('shiftSlot', fn (Builder $slot) => $slot->where('date', $date))
            ->with(['shiftSlot', 'attendance.corrector:id,name', 'staffProfile.user:id,name'])
            ->get()
            ->sortBy(fn (ShiftAssignment $a) => sprintf('%s %010d %s', $a->shiftSlot->start_time, $a->shiftSlot->post_id ?? 0, $a->staffProfile->user->name))
            ->values();

        $states = $assignments->mapWithKeys(fn (ShiftAssignment $a) => [$a->id => $this->attendance->state($a, $now)]);
        $filtered = $request->validated('state')
            ? $assignments->filter(fn (ShiftAssignment $a) => $states[$a->id] === $request->validated('state'))->values()
            : $assignments;

        return ShiftAttendanceResource::collection($filtered)->additional([
            'date' => $date,
            'summary' => [
                'total' => $assignments->count(),
                'on_time' => $assignments->filter(fn ($a) => $a->attendance?->status?->value === 'on_time')->count(),
                'late' => $assignments->filter(fn ($a) => $a->attendance?->status?->value === 'late')->count(),
                'absent' => $states->filter(fn ($state) => in_array($state, ['absent', 'missed'], true))->count(),
                'in_progress' => $states->filter(fn ($state) => $state === 'in_progress')->count(),
                'missing_checkout' => $states->filter(fn ($state) => $state === 'missing_checkout')->count(),
                'waiting' => $states->filter(fn ($state) => in_array($state, ['upcoming', 'open'], true))->count(),
            ],
        ]);
    }

    public function correct(CorrectAttendanceRequest $request, ShiftAssignment $shiftAssignment): JsonResponse
    {
        $shiftAssignment->load(['shiftSlot', 'attendance']);
        $this->attendance->correct($shiftAssignment, $request->validated(), $request->user());

        return response()->json([
            'message' => 'Presensi berhasil dikoreksi.',
            'data' => new ShiftAttendanceResource($shiftAssignment->load(['attendance.corrector:id,name', 'staffProfile.user:id,name'])),
        ]);
    }
}
