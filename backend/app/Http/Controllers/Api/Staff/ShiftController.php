<?php

namespace App\Http\Controllers\Api\Staff;

use App\Enums\SchedulePeriodStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Staff\ListShiftsRequest;
use App\Http\Resources\StaffShiftResource;
use App\Models\ShiftAssignment;
use Carbon\Carbon;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * Jadwal shift staff yang login. Hanya periode yang sudah dipublikasikan yang terlihat.
 */
class ShiftController extends Controller
{
    public function index(ListShiftsRequest $request): AnonymousResourceCollection
    {
        $from = $request->validated('from') ?? today()->startOfWeek(Carbon::MONDAY)->toDateString();
        $to = $request->validated('to') ?? Carbon::parse($from)->addDays(27)->toDateString();

        $shifts = ShiftAssignment::query()
            ->where('staff_profile_id', $request->user()->staffProfile->id)
            ->whereHas('shiftSlot', fn ($slot) => $slot
                ->whereBetween('date', [$from, $to])
                ->whereHas('schedulePeriod', fn ($period) => $period->where('status', SchedulePeriodStatus::Published)))
            ->with('shiftSlot.assignments.staffProfile.user:id,name')
            ->get()
            ->sortBy(fn (ShiftAssignment $shift) => $shift->shiftSlot->date->toDateString().' '.$shift->shiftSlot->start_time)
            ->values();

        return StaffShiftResource::collection($shifts)->additional([
            'meta' => [
                'from' => $from,
                'to' => $to,
                'total_minutes' => $shifts->sum(fn (ShiftAssignment $shift) => $shift->shiftSlot->durationMinutes()),
            ],
        ]);
    }
}
