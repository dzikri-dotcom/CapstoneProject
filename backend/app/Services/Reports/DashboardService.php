<?php

namespace App\Services\Reports;

use App\Enums\AttendanceStatus;
use App\Enums\DayOfWeek;
use App\Models\RoundRobinState;
use App\Models\SchedulePeriod;
use App\Models\ShiftAssignment;
use App\Models\ShiftSlot;
use App\Models\StaffProfile;
use App\Services\Attendance\AttendanceService;
use App\Services\Payroll\PayrollService;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;

class DashboardService
{
    public function __construct(
        private readonly AttendanceService $attendance,
        private readonly PayrollService $payroll,
    ) {
    }

    public function admin(): array
    {
        $this->attendance->markAbsent();
        $now = now();
        $today = $now->toDateString();

        // Shift hari ini (jadwal dipublikasikan) beserta status presensi setiap staff.
        $todayAssignments = $this->attendance->publishedAssignments()
            ->whereHas('shiftSlot', fn (Builder $slot) => $slot->where('date', $today))
            ->with(['shiftSlot', 'attendance', 'staffProfile.user:id,name'])
            ->get()
            ->sortBy(fn (ShiftAssignment $a) => sprintf('%s %010d %s', $a->shiftSlot->start_time, $a->shiftSlot->post_id ?? 0, $a->staffProfile->user->name));

        $todaySlots = $todayAssignments->groupBy('shift_slot_id')->map(function ($items) use ($now) {
            $slot = $items->first()->shiftSlot;

            return [
                'id' => $slot->id,
                'shift_name' => $slot->shift_name,
                'post_name' => $slot->post_name,
                'start_time' => substr($slot->start_time, 0, 5),
                'end_time' => substr($slot->end_time, 0, 5),
                'required_staff' => $slot->required_staff,
                'staff' => $items->map(fn (ShiftAssignment $a) => $this->attendance->describe($a, $now))->values()->all(),
            ];
        })->values();

        $onDuty = $todayAssignments
            ->filter(fn (ShiftAssignment $a) => $this->attendance->state($a, $now) === 'in_progress')
            ->map(fn (ShiftAssignment $a) => [
                'name' => $a->staffProfile->user->name,
                'shift' => $a->shiftSlot->label(),
                'check_in_time' => $a->attendance->check_in_at->format('H:i'),
            ])->values();

        // Slot kurang staff mulai hari ini s.d. 14 hari ke depan (draft maupun dipublikasikan).
        $understaffed = ShiftSlot::query()
            ->whereBetween('date', [$today, $now->copy()->addDays(14)->toDateString()])
            ->withCount('assignments')
            ->with('schedulePeriod:id,status')
            ->orderBy('date')->orderBy('start_time')->orderBy('post_id')
            ->get()
            ->filter(fn (ShiftSlot $slot) => $slot->assignments_count < $slot->required_staff)
            ->map(fn (ShiftSlot $slot) => [
                'id' => $slot->id,
                'schedule_period_id' => $slot->schedule_period_id,
                'period_status' => $slot->schedulePeriod->status->value,
                'date' => $slot->date->toDateString(),
                'day_label' => DayOfWeek::from($slot->date->dayOfWeekIso)->label(),
                'start_time' => substr($slot->start_time, 0, 5),
                'end_time' => substr($slot->end_time, 0, 5),
                'post_name' => $slot->post_name,
                'missing' => $slot->required_staff - $slot->assignments_count,
            ])->values();

        // Ringkasan kehadiran bulan ini.
        $monthStatuses = $this->attendance->publishedAssignments()
            ->whereHas('shiftSlot', fn (Builder $slot) => $slot->whereBetween('date', [$now->copy()->startOfMonth()->toDateString(), $today]))
            ->whereHas('attendance')
            ->with('attendance:id,shift_assignment_id,status')
            ->get()
            ->countBy(fn (ShiftAssignment $a) => $a->attendance->status->value);
        $monthTotal = $monthStatuses->sum();
        $present = ($monthStatuses['on_time'] ?? 0) + ($monthStatuses['late'] ?? 0);

        $latestPeriod = SchedulePeriod::query()->orderByDesc('start_date')->first();
        $nextPointer = RoundRobinState::current()->nextStaff()->with('user:id,name')->first();

        return [
            'date' => $today,
            'active_staff' => StaffProfile::active()->count(),
            'today_shift_count' => $todayAssignments->unique(fn (ShiftAssignment $a) => $a->shiftSlot->start_time.'-'.$a->shiftSlot->end_time)->count(),
            'today_slots' => $todaySlots->all(),
            'on_duty' => $onDuty->all(),
            'understaffed' => $understaffed->all(),
            'month_attendance' => [
                'total' => $monthTotal,
                'on_time' => $monthStatuses['on_time'] ?? 0,
                'late' => $monthStatuses['late'] ?? 0,
                'absent' => $monthStatuses['absent'] ?? 0,
                'rate' => $monthTotal ? round($present / $monthTotal * 100, 1) : null,
            ],
            'latest_period' => $latestPeriod ? [
                'id' => $latestPeriod->id,
                'start_date' => $latestPeriod->start_date->toDateString(),
                'end_date' => $latestPeriod->end_date->toDateString(),
                'status' => $latestPeriod->status->value,
            ] : null,
            'next_pointer' => $nextPointer?->user->name,
        ];
    }

    public function staff(StaffProfile $staff): array
    {
        $this->attendance->markAbsent();
        $now = now();
        $monthStart = $now->copy()->startOfMonth();
        $monthEnd = $now->copy()->endOfMonth();

        $status = $this->attendance->statusFor($staff);
        $estimate = $this->payroll->estimate($staff, $monthStart, $now);

        $monthAssignments = $this->attendance->publishedAssignments()
            ->where('staff_profile_id', $staff->id)
            ->whereHas('shiftSlot', fn (Builder $slot) => $slot->whereBetween('date', [$monthStart->toDateString(), $monthEnd->toDateString()]))
            ->with(['shiftSlot', 'attendance'])
            ->get();

        $counts = $monthAssignments->filter(fn ($a) => $a->attendance)->countBy(fn ($a) => $a->attendance->status->value);
        $upcoming = $monthAssignments->filter(fn (ShiftAssignment $a) => Carbon::parse($a->shiftSlot->date->toDateString().' '.$a->shiftSlot->start_time)->gt($now))->count();

        return [
            'current' => $status['check_out'] ?? $status['check_in'],
            'next' => $status['next'],
            'month' => [
                'label' => $monthStart->translatedFormat('F Y'),
                'shifts_done' => $estimate['shifts'],
                'upcoming_shifts' => $upcoming,
                'paid_minutes' => $estimate['paid_minutes'],
                'estimated_amount' => $estimate['amount'],
                'on_time' => $counts[AttendanceStatus::OnTime->value] ?? 0,
                'late' => $counts[AttendanceStatus::Late->value] ?? 0,
                'absent' => $counts[AttendanceStatus::Absent->value] ?? 0,
            ],
        ];
    }
}
