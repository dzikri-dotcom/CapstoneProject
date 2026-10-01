<?php

namespace App\Services\Scheduling;

use App\Enums\AssignmentSource;
use App\Models\ClassSchedule;
use App\Models\SchedulePeriod;
use App\Models\ShiftAssignment;
use App\Models\ShiftSlot;
use App\Models\StaffProfile;
use App\Services\SettingService;
use App\Support\Time;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Edit manual jadwal oleh admin (tambah / hapus / pindah / ganti / tukar staff)
 * beserta peringatan jika staff sedang kuliah atau melebihi batas jam per minggu.
 * Peringatan tidak memblokir: keputusan akhir tetap di tangan admin. Yang diblokir hanya
 * satu staff di dua pos pada jam yang sama, dan pos yang diisi melebihi kapasitasnya.
 */
class ScheduleEditor
{
    public function __construct(private readonly SettingService $settings)
    {
    }

    /*
    |--------------------------------------------------------------------------
    | Analisis & peringatan
    |--------------------------------------------------------------------------
    */

    /**
     * Daftar staff aktif untuk slot tertentu, lengkap dengan status & peringatannya.
     */
    public function candidates(ShiftSlot $slot): array
    {
        $staff = StaffProfile::active()->with('user:id,name')->get();
        $classes = $this->classesByStaff($staff->pluck('id')->all());
        $weekly = $this->weeklyMinutes(collect([$slot->date]));
        $week = $slot->date->format('o-W');
        $assignedIds = $slot->assignments()->pluck('staff_profile_id')->all();
        // Staff yang sedang bertugas di pos lain pada jam yang beririsan → tidak bisa dipilih.
        $busyElsewhere = $this->overlappingAssignments($slot)
            ->where('shift_slot_id', '!=', $slot->id)
            ->mapWithKeys(fn (ShiftAssignment $a) => [$a->staff_profile_id => $a->shiftSlot->label()])
            ->all();

        return $staff
            ->map(function (StaffProfile $member) use ($slot, $classes, $weekly, $week, $assignedIds, $busyElsewhere) {
                $isAssigned = in_array($member->id, $assignedIds, true);
                $weekMinutes = $weekly[$member->id][$week] ?? 0;
                $busyIn = $busyElsewhere[$member->id] ?? null;

                return [
                    'staff_profile_id' => $member->id,
                    'name' => $member->user->name,
                    'nim' => $member->nim,
                    'is_assigned' => $isAssigned,
                    'busy_in' => $busyIn,
                    'week_minutes' => $weekMinutes,
                    'max_minutes' => $this->maxMinutesFor($member),
                    'warnings' => $isAssigned || $busyIn ? [] : $this->warningsFor(
                        $classes[$member->id] ?? [],
                        $slot,
                        $weekMinutes + $this->slotMinutes($slot),
                        $this->maxMinutesFor($member),
                    ),
                ];
            })
            ->sortBy([
                fn ($a, $b) => $a['is_assigned'] <=> $b['is_assigned'],
                fn ($a, $b) => isset($a['busy_in']) <=> isset($b['busy_in']),
                fn ($a, $b) => count($a['warnings']) <=> count($b['warnings']),
                fn ($a, $b) => $a['week_minutes'] <=> $b['week_minutes'],
                fn ($a, $b) => $a['name'] <=> $b['name'],
            ])
            ->values()
            ->all();
    }

    /**
     * Peringatan untuk setiap penugasan di periode + rekap jam per staff (untuk halaman edit).
     *
     * @return array{warnings: array<int, list<array{type:string, message:string}>>, staff_hours: list<array<string, mixed>>}
     */
    public function analyze(SchedulePeriod $period): array
    {
        $slots = $period->shiftSlots;
        $assignments = $slots->flatMap->assignments;
        $classes = $this->classesByStaff($assignments->pluck('staff_profile_id')->unique()->all());
        $weekly = $this->weeklyMinutes($slots->pluck('date'));
        $limits = $this->maxMinutesByStaff($assignments->pluck('staff_profile_id')->unique()->all());

        $warnings = [];
        foreach ($slots as $slot) {
            $week = $slot->date->format('o-W');
            foreach ($slot->assignments as $assignment) {
                $list = $this->warningsFor(
                    $classes[$assignment->staff_profile_id] ?? [],
                    $slot,
                    $weekly[$assignment->staff_profile_id][$week] ?? 0,
                    $limits[$assignment->staff_profile_id] ?? $this->defaultMaxMinutes(),
                );
                if ($list) {
                    $warnings[$assignment->id] = $list;
                }
            }
        }

        // Rekap jam per staff dalam periode ini (termasuk staff aktif yang belum dapat shift).
        $minutes = [];
        foreach ($slots as $slot) {
            foreach ($slot->assignments as $assignment) {
                $minutes[$assignment->staff_profile_id]['minutes'] = ($minutes[$assignment->staff_profile_id]['minutes'] ?? 0) + $this->slotMinutes($slot);
                $minutes[$assignment->staff_profile_id]['shifts'] = ($minutes[$assignment->staff_profile_id]['shifts'] ?? 0) + 1;
            }
        }

        $staffHours = StaffProfile::query()
            ->with('user:id,name')
            ->where(fn ($query) => $query->where('is_active', true)->orWhereIn('id', array_keys($minutes)))
            ->get()
            ->map(fn (StaffProfile $staff) => [
                'staff_profile_id' => $staff->id,
                'name' => $staff->user->name,
                'is_active' => $staff->is_active,
                'minutes' => $minutes[$staff->id]['minutes'] ?? 0,
                'max_weekly_minutes' => $this->maxMinutesFor($staff),
                'shifts' => $minutes[$staff->id]['shifts'] ?? 0,
            ])
            ->sortBy('name')
            ->values()
            ->all();

        return ['warnings' => $warnings, 'staff_hours' => $staffHours];
    }

    /**
     * @param  list<array{start:int, end:int, course:?string, day:int}>  $staffClasses
     * @return list<array{type:string, message:string}>
     */
    private function warningsFor(array $staffClasses, ShiftSlot $slot, int $weekMinutesWithSlot, int $maxMinutes): array
    {
        $warnings = [];
        $start = Time::toMinutes($slot->start_time);
        $end = Time::toMinutes($slot->end_time);
        $day = $slot->date->dayOfWeekIso;

        foreach ($staffClasses as $class) {
            if ($class['day'] === $day && Time::overlaps($start, $end, $class['start'], $class['end'])) {
                $warnings[] = [
                    'type' => 'class_conflict',
                    'message' => sprintf(
                        'Ada kuliah%s %s–%s.',
                        $class['course'] ? " {$class['course']}" : '',
                        Time::format($class['start']),
                        Time::format($class['end']),
                    ),
                ];
                break;
            }
        }

        if ($weekMinutesWithSlot > $maxMinutes) {
            $warnings[] = [
                'type' => 'max_weekly_hours',
                'message' => sprintf(
                    'Total minggu ini %s, melebihi batas %s.',
                    Time::duration($weekMinutesWithSlot),
                    Time::duration($maxMinutes),
                ),
            ];
        }

        return $warnings;
    }

    /*
    |--------------------------------------------------------------------------
    | Perubahan manual
    |--------------------------------------------------------------------------
    */

    public function add(ShiftSlot $slot, StaffProfile $staff): ShiftAssignment
    {
        if (! $staff->is_active) {
            $this->fail('staff_profile_id', 'Staff nonaktif tidak bisa dijadwalkan.');
        }
        $this->ensureHasRoom($slot);
        $this->ensureFree($slot, $staff->id);

        return $slot->assignments()->create([
            'staff_profile_id' => $staff->id,
            'source' => AssignmentSource::Manual,
        ]);
    }

    public function remove(ShiftAssignment $assignment): void
    {
        $this->ensureEditable($assignment);
        $assignment->delete();
    }

    /** Pindahkan penugasan ke slot lain dalam periode yang sama. */
    public function move(ShiftAssignment $assignment, ShiftSlot $target): ShiftAssignment
    {
        $this->ensureEditable($assignment);

        if ($target->schedule_period_id !== $assignment->shiftSlot->schedule_period_id) {
            $this->fail('shift_slot_id', 'Slot tujuan harus berada di periode yang sama.');
        }
        if ($target->id !== $assignment->shift_slot_id) {
            $this->ensureHasRoom($target);
            $this->ensureFree($target, $assignment->staff_profile_id, [$assignment->id]);
        }

        $assignment->update(['shift_slot_id' => $target->id, 'source' => AssignmentSource::Manual]);

        return $assignment->refresh();
    }

    /** Ganti staff pada penugasan ini dengan staff lain. */
    public function replace(ShiftAssignment $assignment, StaffProfile $staff): ShiftAssignment
    {
        $this->ensureEditable($assignment);

        if (! $staff->is_active) {
            $this->fail('staff_profile_id', 'Staff nonaktif tidak bisa dijadwalkan.');
        }
        if ($staff->id !== $assignment->staff_profile_id) {
            $this->ensureFree($assignment->shiftSlot, $staff->id);
        }

        $assignment->update(['staff_profile_id' => $staff->id, 'source' => AssignmentSource::Manual]);

        return $assignment->refresh();
    }

    /** Tukar staff antara dua penugasan di slot berbeda. */
    public function swap(ShiftAssignment $first, ShiftAssignment $second): void
    {
        $this->ensureEditable($first);
        $this->ensureEditable($second);

        if ($first->shift_slot_id === $second->shift_slot_id) {
            $this->fail('second_assignment_id', 'Kedua staff berada di slot yang sama sehingga tidak perlu ditukar.');
        }
        if ($first->staff_profile_id === $second->staff_profile_id) {
            $this->fail('second_assignment_id', 'Staff yang sama tidak bisa ditukar dengan dirinya sendiri.');
        }
        $this->ensureFree($second->shiftSlot, $first->staff_profile_id, [$first->id]);
        $this->ensureFree($first->shiftSlot, $second->staff_profile_id, [$second->id]);

        DB::transaction(function () use ($first, $second) {
            $firstStaff = $first->staff_profile_id;
            $first->update(['staff_profile_id' => $second->staff_profile_id, 'source' => AssignmentSource::Manual]);
            $second->update(['staff_profile_id' => $firstStaff, 'source' => AssignmentSource::Manual]);
        });
    }

    /**
     * Peringatan untuk satu penugasan setelah diubah (ditampilkan sebagai notifikasi).
     *
     * @return list<array{type:string, message:string}>
     */
    public function warningsForAssignment(ShiftAssignment $assignment): array
    {
        $slot = $assignment->shiftSlot()->first();
        $weekly = $this->weeklyMinutes(collect([$slot->date]));

        return $this->warningsFor(
            $this->classesByStaff([$assignment->staff_profile_id])[$assignment->staff_profile_id] ?? [],
            $slot,
            $weekly[$assignment->staff_profile_id][$slot->date->format('o-W')] ?? 0,
            $this->maxMinutesByStaff([$assignment->staff_profile_id])[$assignment->staff_profile_id] ?? $this->defaultMaxMinutes(),
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Helper
    |--------------------------------------------------------------------------
    */

    private function defaultMaxMinutes(): int
    {
        return $this->settings->get('max_weekly_hours') * 60;
    }

    /** Batas jam mingguan yang berlaku untuk staff: batas khususnya, atau batas umum. */
    private function maxMinutesFor(StaffProfile $staff): int
    {
        return $staff->max_weekly_hours ? $staff->max_weekly_hours * 60 : $this->defaultMaxMinutes();
    }

    /**
     * @param  list<int>  $staffIds
     * @return array<int, int>
     */
    private function maxMinutesByStaff(array $staffIds): array
    {
        return StaffProfile::query()->whereIn('id', $staffIds)->get()
            ->mapWithKeys(fn (StaffProfile $staff) => [$staff->id => $this->maxMinutesFor($staff)])
            ->all();
    }

    private function ensureEditable(ShiftAssignment $assignment): void
    {
        if ($assignment->attendance()->exists()) {
            $this->fail('assignment', 'Penugasan ini sudah memiliki data presensi sehingga tidak bisa diubah.');
        }
    }

    /** Satu slot (pos) tidak boleh diisi melebihi kebutuhannya — maksimal 2 orang per pos. */
    private function ensureHasRoom(ShiftSlot $slot): void
    {
        if ($slot->assignments()->count() >= $slot->required_staff) {
            $this->fail('shift_slot_id', sprintf(
                '%s sudah penuh (maksimal %d orang).',
                $slot->post_name ?? 'Slot ini',
                $slot->required_staff,
            ));
        }
    }

    /**
     * Staff tidak boleh sudah ada di slot ini maupun di slot lain (pos lain) yang jamnya beririsan.
     *
     * @param  list<int>  $ignoreAssignmentIds  penugasan yang sedang dipindah/ditukar
     */
    private function ensureFree(ShiftSlot $slot, int $staffId, array $ignoreAssignmentIds = []): void
    {
        $conflict = $this->overlappingAssignments($slot)
            ->where('staff_profile_id', $staffId)
            ->whereNotIn('id', $ignoreAssignmentIds)
            ->first();

        if ($conflict) {
            $name = StaffProfile::with('user:id,name')->find($staffId)?->user->name ?? 'Staff';
            $this->fail('staff_profile_id', "{$name} sudah ditugaskan di slot {$this->slotLabel($conflict->shiftSlot)}.");
        }
    }

    /**
     * Semua penugasan (periode mana pun) pada tanggal slot ini yang jamnya beririsan dengan slot.
     *
     * @return Collection<int, ShiftAssignment>
     */
    private function overlappingAssignments(ShiftSlot $slot): Collection
    {
        return ShiftAssignment::query()
            ->whereHas('shiftSlot', fn ($query) => $query
                ->whereDate('date', $slot->date->toDateString())
                ->where('start_time', '<', $slot->end_time)
                ->where('end_time', '>', $slot->start_time))
            ->with('shiftSlot')
            ->get();
    }

    private function slotLabel(ShiftSlot $slot): string
    {
        return $slot->date->translatedFormat('l, j M').' '.$slot->label();
    }

    private function fail(string $field, string $message): never
    {
        throw ValidationException::withMessages([$field => $message]);
    }

    private function slotMinutes(ShiftSlot $slot): int
    {
        return Time::toMinutes($slot->end_time) - Time::toMinutes($slot->start_time);
    }

    /**
     * @return array<int, list<array{day:int, start:int, end:int, course:?string}>>
     */
    private function classesByStaff(array $staffIds): array
    {
        return ClassSchedule::query()
            ->whereIn('staff_profile_id', $staffIds)
            ->get()
            ->groupBy('staff_profile_id')
            ->map(fn (Collection $classes) => $classes->map(fn (ClassSchedule $class) => [
                'day' => $class->day_of_week,
                'start' => Time::toMinutes($class->start_time),
                'end' => Time::toMinutes($class->end_time),
                'course' => $class->course_name,
            ])->all())
            ->all();
    }

    /**
     * Total menit shift per staff per minggu ISO (semua periode) untuk minggu-minggu yang memuat tanggal tsb.
     *
     * @param  Collection<int, Carbon>  $dates
     * @return array<int, array<string, int>>
     */
    private function weeklyMinutes(Collection $dates): array
    {
        if ($dates->isEmpty()) {
            return [];
        }

        $rows = ShiftAssignment::query()
            ->join('shift_slots', 'shift_slots.id', '=', 'shift_assignments.shift_slot_id')
            ->whereBetween('shift_slots.date', [
                $dates->min()->copy()->startOfWeek(Carbon::MONDAY)->toDateString(),
                $dates->max()->copy()->endOfWeek(Carbon::SUNDAY)->toDateString(),
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
}
