<?php

namespace App\Services\Reports;

use App\Enums\AssignmentSource;
use App\Enums\AttendanceStatus;
use App\Models\SchedulePeriod;
use App\Models\ShiftAssignment;
use App\Models\StaffProfile;
use App\Services\Attendance\AttendanceService;
use App\Services\Scheduling\AvailabilityService;
use App\Support\Time;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * Laporan untuk koordinator & bahan penelitian:
 *  - keadilan distribusi jadwal (jam shift per staff + statistik pemerataan),
 *  - rekap kehadiran per staff.
 */
class ReportService
{
    public function __construct(
        private readonly AttendanceService $attendance,
        private readonly AvailabilityService $availability,
    ) {
    }

    /**
     * Keadilan distribusi: total jam shift yang DIJADWALKAN per staff pada rentang tanggal
     * (semua periode yang sudah di-generate, draft maupun dipublikasikan).
     */
    public function fairness(string $from, string $to): array
    {
        $assignments = ShiftAssignment::query()
            ->whereHas('shiftSlot', fn (Builder $slot) => $slot->whereBetween('date', [$from, $to]))
            ->with('shiftSlot')
            ->get();

        $staff = $this->staffInvolved($assignments);
        $byStaff = $assignments->groupBy('staff_profile_id');

        $rows = $staff->map(function (StaffProfile $member) use ($byStaff) {
            $items = $byStaff->get($member->id, collect());
            $availability = $this->availability->forStaff($member);

            return [
                'staff_profile_id' => $member->id,
                'name' => $member->user->name,
                'nim' => $member->nim,
                'is_active' => $member->is_active,
                'minutes' => $items->sum(fn (ShiftAssignment $a) => $this->minutes($a)),
                'shifts' => $items->count(),
                'manual_shifts' => $items->where('source', AssignmentSource::Manual)->count(),
                'free_minutes_per_week' => $availability['total_free_minutes'],
            ];
        })->sortByDesc('minutes')->values();

        $periods = SchedulePeriod::query()
            ->whereNotNull('generated_at')
            ->where('start_date', '<=', $to)
            ->where('end_date', '>=', $from)
            ->orderBy('start_date')
            ->get()
            ->map(function (SchedulePeriod $period) use ($assignments, $staff) {
                $inPeriod = $assignments->filter(fn (ShiftAssignment $a) => $a->shiftSlot->schedule_period_id === $period->id);
                $minutes = $staff->mapWithKeys(fn ($member) => [$member->id => 0])->all();
                foreach ($inPeriod as $assignment) {
                    $minutes[$assignment->staff_profile_id] = ($minutes[$assignment->staff_profile_id] ?? 0) + $this->minutes($assignment);
                }

                return [
                    'id' => $period->id,
                    'start_date' => $period->start_date->toDateString(),
                    'end_date' => $period->end_date->toDateString(),
                    'status' => $period->status->value,
                    'stats' => DistributionStats::of(array_values($minutes)),
                ];
            });

        return [
            'from' => $from,
            'to' => $to,
            'stats' => DistributionStats::of($rows->pluck('minutes')->all()),
            'staff' => $rows->all(),
            'periods' => $periods->all(),
        ];
    }

    /**
     * Rekap kehadiran per staff pada shift yang sudah berjalan (jadwal dipublikasikan).
     */
    public function attendance(string $from, string $to): array
    {
        $this->attendance->markAbsent();

        $assignments = $this->attendance->publishedAssignments()
            ->whereHas('shiftSlot', fn (Builder $slot) => $slot->whereBetween('date', [$from, $to]))
            ->whereHas('attendance')
            ->with(['shiftSlot', 'attendance'])
            ->get();

        $byStaff = $assignments->groupBy('staff_profile_id');

        $rows = $this->staffInvolved($assignments)->map(function (StaffProfile $member) use ($byStaff) {
            $items = $byStaff->get($member->id, collect());
            $status = fn (AttendanceStatus $s) => $items->filter(fn ($a) => $a->attendance->status === $s)->count();
            $present = $status(AttendanceStatus::OnTime) + $status(AttendanceStatus::Late);

            return [
                'staff_profile_id' => $member->id,
                'name' => $member->user->name,
                'nim' => $member->nim,
                'shifts' => $items->count(),
                'on_time' => $status(AttendanceStatus::OnTime),
                'late' => $status(AttendanceStatus::Late),
                'absent' => $status(AttendanceStatus::Absent),
                'missing_checkout' => $items->filter(fn ($a) => $a->attendance->check_in_at && ! $a->attendance->check_out_at)->count(),
                'corrected' => $items->filter(fn ($a) => $a->attendance->is_corrected)->count(),
                'worked_minutes' => $items->sum(fn ($a) => $a->attendance->check_in_at && $a->attendance->check_out_at
                    ? (int) $a->attendance->check_in_at->diffInMinutes($a->attendance->check_out_at)
                    : 0),
                'attendance_rate' => $items->count() ? round($present / $items->count() * 100, 1) : 0.0,
            ];
        })->filter(fn ($row) => $row['shifts'] > 0)->sortBy('name')->values();

        $totals = [
            'shifts' => $rows->sum('shifts'),
            'on_time' => $rows->sum('on_time'),
            'late' => $rows->sum('late'),
            'absent' => $rows->sum('absent'),
            'missing_checkout' => $rows->sum('missing_checkout'),
            'worked_minutes' => $rows->sum('worked_minutes'),
        ];
        $totals['attendance_rate'] = $totals['shifts'] ? round(($totals['on_time'] + $totals['late']) / $totals['shifts'] * 100, 1) : 0.0;

        return ['from' => $from, 'to' => $to, 'totals' => $totals, 'staff' => $rows->all()];
    }

    /** Staff aktif + staff nonaktif yang punya penugasan pada rentang tersebut. */
    private function staffInvolved(Collection $assignments): Collection
    {
        $ids = $assignments->pluck('staff_profile_id')->unique()->all();

        return StaffProfile::query()
            ->with('user:id,name')
            ->where(fn (Builder $q) => $q->where('is_active', true)->orWhereIn('id', $ids))
            ->get();
    }

    private function minutes(ShiftAssignment $assignment): int
    {
        return Time::toMinutes($assignment->shiftSlot->end_time) - Time::toMinutes($assignment->shiftSlot->start_time);
    }
}
