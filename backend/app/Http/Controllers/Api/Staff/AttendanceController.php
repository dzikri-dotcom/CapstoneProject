<?php

namespace App\Http\Controllers\Api\Staff;

use App\Http\Controllers\Controller;
use App\Http\Requests\Staff\AttendanceHistoryRequest;
use App\Http\Requests\Staff\AttendanceTokenRequest;
use App\Http\Resources\ShiftAttendanceResource;
use App\Models\ShiftAssignment;
use App\Services\Attendance\AttendanceService;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * Presensi staff: status shift saat ini, check-in/check-out dengan token QR, dan riwayat.
 */
class AttendanceController extends Controller
{
    public function __construct(private readonly AttendanceService $attendance)
    {
    }

    public function current(Request $request): JsonResponse
    {
        $this->attendance->markAbsent();

        return response()->json(['data' => $this->attendance->statusFor($request->user()->staffProfile)]);
    }

    /**
     * Satu scan QR: sistem menentukan check-in, check-out, atau pergantian sesi berdasarkan sesi staff.
     */
    public function scan(AttendanceTokenRequest $request): JsonResponse
    {
        $result = $this->attendance->scan($request->user()->staffProfile, $request->validated('token'));

        return response()->json([
            'message' => $result['message'],
            'action' => $result['action'],
            'data' => $this->attendance->statusFor($request->user()->staffProfile),
        ]);
    }

    public function checkIn(AttendanceTokenRequest $request): JsonResponse
    {
        $record = $this->attendance->checkIn($request->user()->staffProfile, $request->validated('token'));

        return response()->json([
            'message' => sprintf('Check-in berhasil pukul %s — %s.', $record->check_in_at->format('H:i'), $record->status->label()),
            'data' => $this->attendance->statusFor($request->user()->staffProfile),
        ]);
    }

    public function checkOut(AttendanceTokenRequest $request): JsonResponse
    {
        $record = $this->attendance->checkOut($request->user()->staffProfile, $request->validated('token'));

        return response()->json([
            'message' => sprintf('Check-out berhasil pukul %s. Terima kasih!', $record->check_out_at->format('H:i')),
            'data' => $this->attendance->statusFor($request->user()->staffProfile),
        ]);
    }

    /**
     * Riwayat presensi per bulan (hanya shift yang sudah dimulai).
     */
    public function history(AttendanceHistoryRequest $request): AnonymousResourceCollection
    {
        $this->attendance->markAbsent();

        $month = Carbon::createFromFormat('Y-m', $request->validated('month') ?? now()->format('Y-m'))->startOfMonth();
        $now = now();

        $items = $this->attendance->publishedAssignments()
            ->where('staff_profile_id', $request->user()->staffProfile->id)
            ->whereHas('shiftSlot', fn (Builder $slot) => $slot
                ->whereBetween('date', [$month->toDateString(), $month->copy()->endOfMonth()->toDateString()])
                ->where(fn (Builder $q) => $q
                    ->where('date', '<', $now->toDateString())
                    ->orWhere(fn (Builder $q) => $q->where('date', $now->toDateString())->where('start_time', '<=', $now->format('H:i:s')))))
            ->with(['shiftSlot', 'attendance'])
            ->get()
            ->sortByDesc(fn (ShiftAssignment $a) => $a->shiftSlot->date->toDateString().' '.$a->shiftSlot->start_time)
            ->values();

        $attended = $items->filter(fn (ShiftAssignment $a) => $a->attendance?->check_in_at);

        return ShiftAttendanceResource::collection($items)->additional([
            'month' => $month->format('Y-m'),
            'summary' => [
                'shifts' => $items->count(),
                'on_time' => $items->filter(fn ($a) => $a->attendance?->status?->value === 'on_time')->count(),
                'late' => $items->filter(fn ($a) => $a->attendance?->status?->value === 'late')->count(),
                'absent' => $items->filter(fn ($a) => $a->attendance?->status?->value === 'absent')->count(),
                'worked_minutes' => $attended->sum(fn (ShiftAssignment $a) => $a->attendance->check_out_at
                    ? (int) $a->attendance->check_in_at->diffInMinutes($a->attendance->check_out_at)
                    : 0),
            ],
        ]);
    }
}
