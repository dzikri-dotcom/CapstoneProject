<?php

namespace App\Services\Attendance;

use App\Enums\AttendanceMethod;
use App\Enums\AttendanceStatus;
use App\Enums\DayOfWeek;
use App\Enums\PayrollStatus;
use App\Enums\SchedulePeriodStatus;
use App\Models\Attendance;
use App\Models\PayrollPeriod;
use App\Models\ShiftAssignment;
use App\Models\StaffProfile;
use App\Models\User;
use App\Services\SettingService;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Aturan presensi:
 *  - check-in dibuka `checkin_early_minutes` sebelum shift;
 *  - terlambat jika check-in lewat dari jam mulai + `late_tolerance_minutes`;
 *  - check-in ditutup pada jam mulai + `max_late_minutes` (maksimal keterlambatan):
 *    lewat dari itu staff tidak bisa check-in dan dianggap tidak hadir;
 *  - check-out paling lambat `checkout_grace_minutes` setelah shift berakhir;
 *  - shift yang berakhir tanpa check-in otomatis ditandai tidak hadir.
 * Hanya shift dari periode yang sudah dipublikasikan yang bisa dipresensi.
 *
 * Presensi berbasis SESI: setiap shift adalah satu sesi, dan staff hanya boleh punya satu sesi
 * terbuka (sudah check-in, belum check-out). Satu kali scan QR menjalankan aksi yang sesuai (scan()):
 *  - tidak ada sesi terbuka            → check-in ke shift yang jendela check-in-nya sedang buka;
 *  - ada sesi terbuka                  → check-out sesi itu;
 *  - ada sesi terbuka & shift berikutnya
 *    (mis. shift berurutan) sudah bisa
 *    di-check-in                       → PERGANTIAN SESI: check-out sesi lama lalu check-in sesi baru.
 * Scan ulang dalam DOUBLE_SCAN_MINUTES setelah check-in diabaikan agar tidak langsung check-out.
 */
class AttendanceService
{
    /** Scan ulang dalam rentang ini setelah check-in dianggap scan ganda, bukan check-out. */
    public const DOUBLE_SCAN_MINUTES = 5;

    public const STATE_LABELS = [
        'upcoming' => 'Belum dimulai',
        'open' => 'Belum check-in',
        'in_progress' => 'Sedang bertugas',
        'done' => 'Selesai',
        'missing_checkout' => 'Belum check-out',
        'absent' => 'Tidak hadir',
        'missed' => 'Tidak hadir',
    ];

    public function __construct(
        private readonly SettingService $settings,
        private readonly QrTokenService $qrTokens,
    ) {
    }

    /*
    |--------------------------------------------------------------------------
    | Jendela waktu & status
    |--------------------------------------------------------------------------
    */

    /**
     * @return array{start: Carbon, end: Carbon, check_in_opens: Carbon, late_after: Carbon, check_in_closes: Carbon, check_out_closes: Carbon}
     */
    public function window(ShiftAssignment $assignment): array
    {
        $slot = $assignment->shiftSlot;
        $date = $slot->date->toDateString();
        $start = Carbon::parse("{$date} {$slot->start_time}");
        $end = Carbon::parse("{$date} {$slot->end_time}");

        return [
            'start' => $start,
            'end' => $end,
            'check_in_opens' => $start->copy()->subMinutes($this->settings->get('checkin_early_minutes')),
            'late_after' => $start->copy()->addMinutes($this->settings->get('late_tolerance_minutes')),
            // Batas maksimal keterlambatan, tidak pernah melewati jam selesai shift.
            'check_in_closes' => $start->copy()->addMinutes($this->settings->get('max_late_minutes'))->min($end),
            'check_out_closes' => $end->copy()->addMinutes($this->settings->get('checkout_grace_minutes')),
        ];
    }

    /**
     * Keadaan satu penugasan pada waktu tertentu (dipakai layar staff, monitoring admin, dan riwayat).
     */
    public function state(ShiftAssignment $assignment, ?Carbon $now = null): string
    {
        $now ??= now();
        $window = $this->window($assignment);
        $attendance = $assignment->attendance;

        return match (true) {
            $attendance?->status === AttendanceStatus::Absent => 'absent',
            $attendance?->check_in_at && $attendance->check_out_at => 'done',
            $attendance?->check_in_at && $now->lte($window['check_out_closes']) => 'in_progress',
            (bool) $attendance?->check_in_at => 'missing_checkout',
            $now->lt($window['check_in_opens']) => 'upcoming',
            $now->lt($window['check_in_closes']) => 'open',
            default => 'missed',
        };
    }

    public function describe(ShiftAssignment $assignment, ?Carbon $now = null): array
    {
        $window = $this->window($assignment);
        $state = $this->state($assignment, $now);
        $attendance = $assignment->attendance;
        $slot = $assignment->shiftSlot;

        return [
            'assignment_id' => $assignment->id,
            'date' => $slot->date->toDateString(),
            'day_label' => DayOfWeek::from($slot->date->dayOfWeekIso)->label(),
            'start_time' => $window['start']->format('H:i'),
            'end_time' => $window['end']->format('H:i'),
            'check_in_opens' => $window['check_in_opens']->format('H:i'),
            'late_after' => $window['late_after']->format('H:i'),
            'check_in_closes' => $window['check_in_closes']->format('H:i'),
            'shift_name' => $slot->shift_name,
            'post_name' => $slot->post_name,
            'post_location' => $slot->post_location,
            'check_out_closes' => $window['check_out_closes']->format('H:i'),
            'state' => $state,
            'state_label' => self::STATE_LABELS[$state],
            'staff' => $assignment->relationLoaded('staffProfile') ? [
                'id' => $assignment->staff_profile_id,
                'name' => $assignment->staffProfile->user?->name,
            ] : null,
            'attendance' => $attendance ? [
                'id' => $attendance->id,
                'status' => $attendance->status->value,
                'status_label' => $attendance->status->label(),
                'check_in_time' => $attendance->check_in_at?->format('H:i'),
                'check_out_time' => $attendance->check_out_at?->format('H:i'),
                'check_in_method' => $attendance->check_in_method?->value,
                'check_out_method' => $attendance->check_out_method?->value,
                'worked_minutes' => $attendance->check_in_at && $attendance->check_out_at
                    ? (int) $attendance->check_in_at->diffInMinutes($attendance->check_out_at)
                    : null,
                'is_corrected' => $attendance->is_corrected,
                'correction_note' => $attendance->correction_note,
                'corrected_by' => $attendance->relationLoaded('corrector') ? $attendance->corrector?->name : null,
                'corrected_at' => $attendance->corrected_at?->toIso8601String(),
            ] : null,
        ];
    }

    /**
     * Ringkasan untuk halaman presensi staff: shift yang bisa di-check-in / di-check-out sekarang,
     * daftar shift hari ini, dan shift berikutnya.
     */
    public function statusFor(StaffProfile $staff): array
    {
        $now = now();
        $today = $this->assignmentsOn($staff, $now);

        $action = $this->nextAction($today, $now);
        $checkIn = $action['check_in'];
        $checkOut = $action['check_out'];
        $next = $this->publishedAssignments()
            ->where('staff_profile_id', $staff->id)
            ->whereHas('shiftSlot', fn (Builder $slot) => $slot->where(fn (Builder $q) => $q
                ->where('date', '>', $now->toDateString())
                ->orWhere(fn (Builder $q) => $q->where('date', $now->toDateString())->where('start_time', '>', $now->format('H:i:s')))))
            ->with(['shiftSlot', 'attendance'])
            ->get()
            ->sortBy(fn (ShiftAssignment $a) => $a->shiftSlot->date->toDateString().' '.$a->shiftSlot->start_time)
            ->first();

        return [
            'server_time' => $now->toIso8601String(),
            // Aksi yang dijalankan bila staff scan QR sekarang: check_in | check_out | handover | null.
            'next_action' => $action['type'],
            'check_in' => $checkIn ? $this->describe($checkIn, $now) : null,
            'check_out' => $checkOut ? $this->describe($checkOut, $now) : null,
            'today' => $today->map(fn (ShiftAssignment $a) => $this->describe($a, $now))->values()->all(),
            'next' => $next ? $this->describe($next, $now) : null,
        ];
    }

    /*
    |--------------------------------------------------------------------------
    | Check-in / check-out dengan QR
    |--------------------------------------------------------------------------
    */

    public function checkIn(StaffProfile $staff, ?string $token): Attendance
    {
        $this->qrTokens->assertValid($token);
        $now = now();
        $today = $this->assignmentsOn($staff, $now);

        $open = $this->openSession($today, $now);
        if ($open) {
            $this->fail(sprintf('Anda masih bertugas di shift %s. Lakukan check-out terlebih dahulu.', $this->timeRange($open)));
        }

        $assignment = $today->first(fn (ShiftAssignment $a) => $this->canCheckIn($a, $now));
        if (! $assignment) {
            $this->fail($this->checkInUnavailableReason($today, $now));
        }

        return $this->startSession($assignment, $now);
    }

    /**
     * Satu kali scan QR: check-in, check-out, atau pergantian sesi — ditentukan dari sesi staff saat ini.
     *
     * @return array{action:string, message:string, check_in:?Attendance, check_out:?Attendance}
     */
    public function scan(StaffProfile $staff, ?string $token): array
    {
        $this->qrTokens->assertValid($token);
        $now = now();
        $today = $this->assignmentsOn($staff, $now);
        $action = $this->nextAction($today, $now);

        // Scan ganda tepat setelah check-in tidak boleh langsung menutup sesi (check-out maupun pergantian sesi).
        if ($action['check_out']) {
            $this->guardDoubleScan($action['check_out'], $now);
        }

        return match ($action['type']) {
            'check_in' => $this->scanCheckIn($action['check_in'], $now),
            'check_out' => $this->scanCheckOut($action['check_out'], $now),
            'handover' => DB::transaction(function () use ($action, $now) {
                $closed = $this->endSession($action['check_out'], $now);
                $opened = $this->startSession($action['check_in'], $now);

                return [
                    'action' => 'handover',
                    'message' => sprintf(
                        'Pergantian sesi: check-out %s dan check-in %s pukul %s — %s.',
                        $this->sessionLabel($action['check_out']),
                        $this->sessionLabel($action['check_in']),
                        $now->format('H:i'),
                        $opened->status->label(),
                    ),
                    'check_in' => $opened,
                    'check_out' => $closed,
                ];
            }),
            default => $this->fail($this->checkInUnavailableReason($today, $now)),
        };
    }

    private function scanCheckIn(ShiftAssignment $assignment, Carbon $now): array
    {
        $record = $this->startSession($assignment, $now);

        return [
            'action' => 'check_in',
            'message' => sprintf('Check-in %s berhasil pukul %s — %s.', $this->sessionLabel($assignment), $now->format('H:i'), $record->status->label()),
            'check_in' => $record,
            'check_out' => null,
        ];
    }

    private function guardDoubleScan(ShiftAssignment $open, Carbon $now): void
    {
        $checkedInAt = $open->attendance->check_in_at;
        if ($checkedInAt->diffInMinutes($now) < self::DOUBLE_SCAN_MINUTES) {
            $this->fail(sprintf(
                'Anda sudah check-in pukul %s untuk %s. Scan lagi saat selesai bertugas untuk check-out.',
                $checkedInAt->format('H:i'),
                $this->sessionLabel($open),
            ));
        }
    }

    private function scanCheckOut(ShiftAssignment $assignment, Carbon $now): array
    {
        $record = $this->endSession($assignment, $now);

        return [
            'action' => 'check_out',
            'message' => sprintf('Check-out %s berhasil pukul %s. Terima kasih!', $this->sessionLabel($assignment), $now->format('H:i')),
            'check_in' => null,
            'check_out' => $record,
        ];
    }

    /**
     * Aksi untuk scan berikutnya berdasarkan sesi:
     * check_out = sesi yang sedang terbuka, check_in = sesi lain yang jendela check-in-nya sedang buka.
     *
     * @return array{type:?string, check_in:?ShiftAssignment, check_out:?ShiftAssignment}
     */
    private function nextAction(Collection $today, Carbon $now): array
    {
        $open = $this->openSession($today, $now);
        $next = $today->first(fn (ShiftAssignment $a) => $a->id !== $open?->id && $this->canCheckIn($a, $now));

        return [
            'type' => match (true) {
                $open && $next => 'handover',
                (bool) $open => 'check_out',
                (bool) $next => 'check_in',
                default => null,
            },
            'check_in' => $next,
            'check_out' => $open,
        ];
    }

    /** Sesi terbuka: sudah check-in, belum check-out, dan batas check-out belum lewat. */
    private function openSession(Collection $today, Carbon $now): ?ShiftAssignment
    {
        return $today->first(fn (ShiftAssignment $a) => $this->canCheckOut($a, $now));
    }

    private function startSession(ShiftAssignment $assignment, Carbon $now): Attendance
    {
        $window = $this->window($assignment);

        try {
            $record = Attendance::query()->create([
                'shift_assignment_id' => $assignment->id,
                'staff_profile_id' => $assignment->staff_profile_id,
                'check_in_at' => $now,
                'check_in_method' => AttendanceMethod::Qr,
                'status' => $now->gt($window['late_after']) ? AttendanceStatus::Late : AttendanceStatus::OnTime,
            ]);
        } catch (UniqueConstraintViolationException) {
            // Dua permintaan check-in bersamaan (misal tombol ditekan dua kali).
            $this->fail('Anda sudah check-in untuk shift ini.');
        }

        $assignment->setRelation('attendance', $record);

        return $record;
    }

    private function endSession(ShiftAssignment $assignment, Carbon $now): Attendance
    {
        $assignment->attendance->update([
            'check_out_at' => $now,
            'check_out_method' => AttendanceMethod::Qr,
        ]);

        return $assignment->attendance;
    }

    /** "Shift 1 (06:30–09:30, Pos 1)" atau "shift 08:00–10:00" untuk jadwal lama tanpa nama shift. */
    private function sessionLabel(ShiftAssignment $assignment): string
    {
        $slot = $assignment->shiftSlot;
        if (! $slot->shift_name) {
            return 'shift '.$this->timeRange($assignment);
        }

        return sprintf('%s (%s%s)', $slot->shift_name, $this->timeRange($assignment), $slot->post_name ? ", {$slot->post_name}" : '');
    }

    public function checkOut(StaffProfile $staff, ?string $token): Attendance
    {
        $this->qrTokens->assertValid($token);
        $now = now();

        $assignment = $this->assignmentsOn($staff, $now)->first(fn (ShiftAssignment $a) => $this->canCheckOut($a, $now));
        if (! $assignment) {
            $this->fail('Tidak ada shift yang perlu di-check-out. Pastikan Anda sudah check-in dan batas waktu check-out belum lewat.');
        }

        return $this->endSession($assignment, $now);
    }

    private function canCheckIn(ShiftAssignment $assignment, Carbon $now): bool
    {
        $window = $this->window($assignment);

        return ! $assignment->attendance && $now->gte($window['check_in_opens']) && $now->lt($window['check_in_closes']);
    }

    private function canCheckOut(ShiftAssignment $assignment, Carbon $now): bool
    {
        $attendance = $assignment->attendance;

        return $attendance?->check_in_at
            && ! $attendance->check_out_at
            && $now->lte($this->window($assignment)['check_out_closes']);
    }

    private function checkInUnavailableReason(Collection $today, Carbon $now): string
    {
        $checkedIn = $today->first(fn (ShiftAssignment $a) => $a->attendance?->check_in_at && $now->lt($this->window($a)['end']));
        if ($checkedIn) {
            return sprintf('Anda sudah check-in untuk shift %s.', $this->timeRange($checkedIn));
        }

        $upcoming = $today->first(fn (ShiftAssignment $a) => $now->lt($this->window($a)['check_in_opens']));
        if ($upcoming) {
            return sprintf(
                'Check-in untuk shift %s baru dibuka pukul %s.',
                $this->timeRange($upcoming),
                $this->window($upcoming)['check_in_opens']->format('H:i'),
            );
        }

        $tooLate = $today->first(fn (ShiftAssignment $a) => ! $a->attendance
            && $now->gte($this->window($a)['check_in_closes'])
            && $now->lt($this->window($a)['end']));
        if ($tooLate) {
            return sprintf(
                'Batas maksimal keterlambatan untuk shift %s sudah lewat (check-in ditutup pukul %s). Hubungi koordinator jika perlu koreksi.',
                $this->timeRange($tooLate),
                $this->window($tooLate)['check_in_closes']->format('H:i'),
            );
        }

        return 'Tidak ada shift Anda yang sedang berlangsung saat ini.';
    }

    /*
    |--------------------------------------------------------------------------
    | Tidak hadir otomatis & koreksi admin
    |--------------------------------------------------------------------------
    */

    /**
     * Tandai penugasan yang shift-nya sudah berakhir tanpa check-in sebagai tidak hadir.
     *
     * @return int jumlah penugasan yang ditandai
     */
    public function markAbsent(?Carbon $now = null): int
    {
        $now ??= now();

        $missed = $this->publishedAssignments()
            ->whereDoesntHave('attendance')
            ->whereHas('shiftSlot', fn (Builder $slot) => $slot->where(fn (Builder $q) => $q
                ->where('date', '<', $now->toDateString())
                ->orWhere(fn (Builder $q) => $q->where('date', $now->toDateString())->where('end_time', '<=', $now->format('H:i:s')))))
            ->get(['id', 'staff_profile_id']);

        $rows = $missed->map(fn (ShiftAssignment $a) => [
            'shift_assignment_id' => $a->id,
            'staff_profile_id' => $a->staff_profile_id,
            'status' => AttendanceStatus::Absent->value,
            'is_corrected' => false,
            'created_at' => $now,
            'updated_at' => $now,
        ])->all();

        // insertOrIgnore: aman jika staff check-in/perintah lain berjalan bersamaan.
        return $rows ? Attendance::query()->insertOrIgnore($rows) : 0;
    }

    /**
     * Koreksi manual oleh admin (misal lupa scan / lupa check-out), wajib dengan catatan alasan.
     *
     * @param  array{status:string, check_in_time:?string, check_out_time:?string, correction_note:string}  $data
     */
    public function correct(ShiftAssignment $assignment, array $data, User $admin): Attendance
    {
        $date = $assignment->shiftSlot->date;

        $locked = PayrollPeriod::query()
            ->where('status', PayrollStatus::Final)
            ->where('start_date', '<=', $date->toDateString())
            ->where('end_date', '>=', $date->toDateString())
            ->exists();
        if ($locked) {
            $this->fail('Presensi ini termasuk periode gaji yang sudah final sehingga tidak bisa dikoreksi.');
        }

        $status = AttendanceStatus::from($data['status']);
        $absent = $status === AttendanceStatus::Absent;
        $checkIn = $absent ? null : Carbon::parse("{$date->toDateString()} {$data['check_in_time']}");
        $checkOut = $absent || empty($data['check_out_time']) ? null : Carbon::parse("{$date->toDateString()} {$data['check_out_time']}");

        $attendance = $assignment->attendance ?? new Attendance([
            'shift_assignment_id' => $assignment->id,
            'staff_profile_id' => $assignment->staff_profile_id,
        ]);

        $attendance->fill([
            'status' => $status,
            'check_in_at' => $checkIn,
            'check_out_at' => $checkOut,
            // Metode tetap "qr" jika jamnya tidak diubah admin.
            'check_in_method' => $checkIn ? ($attendance->check_in_at?->equalTo($checkIn) ? $attendance->check_in_method : AttendanceMethod::Manual) : null,
            'check_out_method' => $checkOut ? ($attendance->check_out_at?->equalTo($checkOut) ? $attendance->check_out_method : AttendanceMethod::Manual) : null,
            'is_corrected' => true,
            'correction_note' => $data['correction_note'],
            'corrected_by' => $admin->id,
            'corrected_at' => now(),
        ])->save();

        return $attendance;
    }

    /*
    |--------------------------------------------------------------------------
    | Query helper
    |--------------------------------------------------------------------------
    */

    /** Penugasan pada periode yang sudah dipublikasikan. */
    public function publishedAssignments(): Builder
    {
        return ShiftAssignment::query()->whereHas(
            'shiftSlot.schedulePeriod',
            fn (Builder $period) => $period->where('status', SchedulePeriodStatus::Published),
        );
    }

    /**
     * @return Collection<int, ShiftAssignment>
     */
    private function assignmentsOn(StaffProfile $staff, Carbon $date): Collection
    {
        return $this->publishedAssignments()
            ->where('staff_profile_id', $staff->id)
            ->whereHas('shiftSlot', fn (Builder $slot) => $slot->where('date', $date->toDateString()))
            ->with(['shiftSlot', 'attendance'])
            ->get()
            ->sortBy(fn (ShiftAssignment $a) => $a->shiftSlot->start_time)
            ->values();
    }

    private function timeRange(ShiftAssignment $assignment): string
    {
        $window = $this->window($assignment);

        return $window['start']->format('H:i').'–'.$window['end']->format('H:i');
    }

    private function fail(string $message): never
    {
        throw ValidationException::withMessages(['attendance' => $message]);
    }
}
