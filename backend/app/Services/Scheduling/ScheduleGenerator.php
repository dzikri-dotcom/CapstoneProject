<?php

namespace App\Services\Scheduling;

use App\Enums\AssignmentSource;
use App\Models\ClassSchedule;
use App\Models\OperationalHour;
use App\Models\RoundRobinState;
use App\Models\SchedulePeriod;
use App\Models\SchedulerLog;
use App\Models\ShiftAssignment;
use App\Models\ShiftSlot;
use App\Models\StaffProfile;
use App\Services\SettingService;
use App\Support\Time;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Menjalankan RoundRobinScheduler untuk satu periode: menyiapkan data dari database,
 * lalu menyimpan slot, penugasan (status draft), log proses, dan posisi pointer.
 */
class ScheduleGenerator
{
    public function __construct(private readonly SettingService $settings)
    {
    }

    public function generate(SchedulePeriod $period): SchedulerResult
    {
        if (! $period->isDraft()) {
            throw ValidationException::withMessages([
                'period' => 'Jadwal yang sudah dipublikasikan tidak bisa di-generate ulang. Kembalikan ke draft terlebih dahulu.',
            ]);
        }

        $settings = $this->settings->all();

        // Langkah 2: bentuk slot shift dari jam operasional & durasi shift.
        $slots = SlotBuilder::build(
            $period->start_date,
            $period->end_date,
            $this->operationalHours(),
            $settings['shift_duration_minutes'],
            $settings['staff_per_shift'],
        );

        if ($slots === []) {
            throw ValidationException::withMessages([
                'period' => 'Tidak ada hari operasional aktif dalam rentang tanggal periode ini.',
            ]);
        }

        // Langkah 1 & 3–5: jalankan algoritma Round Robin.
        $result = (new RoundRobinScheduler($settings['max_weekly_hours'] * 60))->run(
            $slots,
            $this->activeStaff(),
            $this->startPointerFor($period),
            $this->minutesFromOtherPeriods($period),
        );

        DB::transaction(fn () => $this->store($period, $slots, $result));

        return $result;
    }

    /**
     * Hapus periode draft. Jika periode ini yang terakhir menggeser pointer global,
     * kembalikan pointer ke posisi sebelum periode ini agar giliran tidak terlewati.
     */
    public function delete(SchedulePeriod $period): void
    {
        if (! $period->isDraft()) {
            throw ValidationException::withMessages([
                'period' => 'Periode yang sudah dipublikasikan tidak bisa dihapus.',
            ]);
        }

        DB::transaction(function () use ($period) {
            $state = RoundRobinState::current();
            if ($state->last_schedule_period_id === $period->id) {
                $state->update([
                    'next_staff_profile_id' => $period->start_pointer_staff_id,
                    'last_schedule_period_id' => null,
                ]);
            }

            $period->delete(); // slot, penugasan, dan log ikut terhapus (cascade)
        });
    }

    /**
     * @return array<int, array{open:int, close:int}>
     */
    private function operationalHours(): array
    {
        return OperationalHour::active()->get()->mapWithKeys(fn (OperationalHour $hour) => [
            $hour->day_of_week => [
                'open' => Time::toMinutes($hour->open_time),
                'close' => Time::toMinutes($hour->close_time),
            ],
        ])->all();
    }

    /**
     * Staff aktif urut ID beserta jadwal kuliahnya per hari (dalam menit).
     */
    private function activeStaff(): array
    {
        return StaffProfile::active()
            ->with(['user:id,name', 'classSchedules'])
            ->orderBy('id')
            ->get()
            ->map(fn (StaffProfile $staff) => [
                'id' => $staff->id,
                'name' => $staff->user->name,
                'classes' => $staff->classSchedules
                    ->groupBy('day_of_week')
                    ->map(fn ($classes) => $classes->map(fn (ClassSchedule $class) => [
                        'start' => Time::toMinutes($class->start_time),
                        'end' => Time::toMinutes($class->end_time),
                        'course' => $class->course_name,
                    ])->values()->all())
                    ->all(),
            ])
            ->all();
    }

    /**
     * Posisi pointer awal:
     *  - generate ulang periode yang sama → pointer awal yang sama (hasil konsisten);
     *  - periode baru → pointer akhir periode sebelumnya, atau posisi tersimpan di round_robin_state.
     */
    private function startPointerFor(SchedulePeriod $period): ?int
    {
        if ($period->generated_at !== null) {
            return $period->start_pointer_staff_id;
        }

        $previous = SchedulePeriod::query()
            ->whereKeyNot($period->id)
            ->whereNotNull('generated_at')
            ->where('end_date', '<', $period->start_date)
            ->orderByDesc('end_date')
            ->first();

        return $previous?->end_pointer_staff_id ?? RoundRobinState::current()->next_staff_profile_id;
    }

    /**
     * Menit shift yang sudah dimiliki staff dari periode LAIN pada minggu yang sama,
     * agar batas jam per minggu tetap benar jika satu minggu terbagi beberapa periode.
     *
     * @return array<int, array<string, int>>
     */
    private function minutesFromOtherPeriods(SchedulePeriod $period): array
    {
        $rows = ShiftAssignment::query()
            ->join('shift_slots', 'shift_slots.id', '=', 'shift_assignments.shift_slot_id')
            ->where('shift_slots.schedule_period_id', '!=', $period->id)
            ->whereBetween('shift_slots.date', [
                $period->start_date->copy()->startOfWeek(Carbon::MONDAY)->toDateString(),
                $period->end_date->copy()->endOfWeek(Carbon::SUNDAY)->toDateString(),
            ])
            ->get(['shift_assignments.staff_profile_id', 'shift_slots.date', 'shift_slots.start_time', 'shift_slots.end_time']);

        $minutes = [];
        foreach ($rows as $row) {
            $week = Carbon::parse($row->date)->format('o-W');
            $minutes[$row->staff_profile_id][$week] = ($minutes[$row->staff_profile_id][$week] ?? 0)
                + Time::toMinutes($row->end_time) - Time::toMinutes($row->start_time);
        }

        return $minutes;
    }

    private function store(SchedulePeriod $period, array $slots, SchedulerResult $result): void
    {
        // Hasil generate sebelumnya diganti seluruhnya.
        SchedulerLog::query()->where('schedule_period_id', $period->id)->delete();
        ShiftSlot::query()->where('schedule_period_id', $period->id)->delete();

        $now = now();
        $slotIds = [];
        foreach ($slots as $slot) {
            $slotIds[$slot['key']] = ShiftSlot::query()->create([
                'schedule_period_id' => $period->id,
                'date' => $slot['date'],
                'start_time' => Time::format($slot['start']),
                'end_time' => Time::format($slot['end']),
                'required_staff' => $slot['required'],
            ])->id;
        }

        $assignments = [];
        foreach ($result->assignments as $slotKey => $staffIds) {
            foreach ($staffIds as $staffId) {
                $assignments[] = [
                    'shift_slot_id' => $slotIds[$slotKey],
                    'staff_profile_id' => $staffId,
                    'source' => AssignmentSource::Auto->value,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }
        }
        ShiftAssignment::query()->insert($assignments);

        $logs = array_map(fn (array $log) => [
            'schedule_period_id' => $period->id,
            'shift_slot_id' => $log['slot_key'] ? $slotIds[$log['slot_key']] : null,
            'step' => $log['step'],
            'staff_profile_id' => $log['staff_id'],
            'pointer_position' => $log['pointer_position'],
            'action' => $log['action']->value,
            'reason' => $log['reason']?->value,
            'message' => $log['message'],
            'meta' => json_encode($log['meta']),
            'created_at' => $now,
        ], $result->logs);
        foreach (array_chunk($logs, 200) as $chunk) {
            SchedulerLog::query()->insert($chunk);
        }

        $period->update([
            'start_pointer_staff_id' => $result->startStaffId,
            'end_pointer_staff_id' => $result->endStaffId,
            'generated_at' => $now,
        ]);

        // Simpan posisi pointer terakhir untuk periode berikutnya (jika ini periode terbaru).
        $hasLaterPeriod = SchedulePeriod::query()
            ->whereKeyNot($period->id)
            ->whereNotNull('generated_at')
            ->where('start_date', '>', $period->start_date)
            ->exists();

        if (! $hasLaterPeriod) {
            RoundRobinState::current()->update([
                'next_staff_profile_id' => $result->endStaffId,
                'last_schedule_period_id' => $period->id,
            ]);
        }
    }
}
