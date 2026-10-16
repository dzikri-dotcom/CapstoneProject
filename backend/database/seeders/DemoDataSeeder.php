<?php

namespace Database\Seeders;

use App\Enums\AttendanceMethod;
use App\Enums\AttendanceStatus;
use App\Enums\PayrollStatus;
use App\Enums\SchedulePeriodStatus;
use App\Models\Attendance;
use App\Models\PayrollPeriod;
use App\Models\SchedulePeriod;
use App\Models\ShiftAssignment;
use App\Services\Payroll\PayrollService;
use App\Services\Scheduling\ScheduleGenerator;
use App\Services\SettingService;
use Carbon\Carbon;
use Illuminate\Database\Seeder;

/**
 * DATA DEMO (opsional) agar semua fitur bisa langsung dilihat hasilnya:
 *
 *   php artisan db:seed --class=DemoDataSeeder
 *
 * - Jadwal Round Robin (shift × pos jaga) untuk minggu lalu & minggu ini (dipublikasikan)
 *   serta minggu depan (draft, untuk dicoba diedit lalu dipublikasikan).
 * - Presensi simulasi dengan pola tetap (acak ber-seed): ±80% tepat waktu, ±10% terlambat,
 *   sebagian pulang lebih cepat, ±3% lupa check-out, ±6% tidak hadir. Shift yang sedang
 *   berlangsung saat seeder dijalankan diisi check-in saja ("sedang bertugas").
 * - Periode gaji bulan berjalan (draft, sudah dihitung).
 *
 * Aman dijalankan ulang: minggu / bulan yang sudah punya periode dilewati, data lain tidak diubah.
 */
class DemoDataSeeder extends Seeder
{
    public function run(ScheduleGenerator $generator, SettingService $settings, PayrollService $payroll): void
    {
        mt_srand(2026);

        $now = now();
        $thisWeek = $now->copy()->startOfWeek(Carbon::MONDAY);
        $tolerance = $settings->get('late_tolerance_minutes');

        // Urut waktu agar pointer Round Robin berlanjut dari minggu ke minggu.
        foreach ([-1 => true, 0 => true, 1 => false] as $offset => $publish) {
            $start = $thisWeek->copy()->addWeeks($offset);
            $end = $start->copy()->addDays(6);

            if ($this->periodExists($start, $end)) {
                $this->command?->warn("Jadwal {$start->toDateString()} – {$end->toDateString()} dilewati: sudah ada periode pada tanggal itu.");

                continue;
            }

            $period = SchedulePeriod::query()->create([
                'start_date' => $start->toDateString(),
                'end_date' => $end->toDateString(),
                'status' => SchedulePeriodStatus::Draft,
            ]);
            $result = $generator->generate($period);

            $attendances = 0;
            if ($publish) {
                $period->update([
                    'status' => SchedulePeriodStatus::Published,
                    'published_at' => $start->copy()->subDays(2)->setTime(15, 0)->min($now),
                ]);

                $assignments = ShiftAssignment::query()
                    ->whereHas('shiftSlot', fn ($query) => $query->where('schedule_period_id', $period->id))
                    ->with('shiftSlot')
                    ->orderBy('id')
                    ->get();

                foreach ($assignments as $assignment) {
                    $attendances += (int) $this->simulateAttendance($assignment, $tolerance, $now);
                }
            }

            $this->command?->info(sprintf(
                'Jadwal %s – %s (%s): %d dari %d slot terpenuhi, %d presensi simulasi.',
                $start->toDateString(),
                $end->toDateString(),
                $publish ? 'dipublikasikan' : 'draft',
                $result->filledSlotCount(),
                $result->slotCount(),
                $attendances,
            ));
        }

        $this->seedPayroll($payroll, $now);
    }

    /*
    |--------------------------------------------------------------------------
    | Jadwal & presensi
    |--------------------------------------------------------------------------
    */

    private function periodExists(Carbon $start, Carbon $end): bool
    {
        return SchedulePeriod::query()
            ->where('start_date', '<=', $end->toDateString())
            ->where('end_date', '>=', $start->toDateString())
            ->exists();
    }

    /**
     * Presensi untuk satu penugasan, sesuai posisi shift terhadap waktu sekarang:
     * sudah selesai → presensi lengkap; sedang berlangsung → check-in saja; belum mulai → tidak ada.
     */
    private function simulateAttendance(ShiftAssignment $assignment, int $tolerance, Carbon $now): bool
    {
        $slot = $assignment->shiftSlot;
        $date = $slot->date->toDateString();
        $shiftStart = Carbon::parse("{$date} {$slot->start_time}");
        $shiftEnd = Carbon::parse("{$date} {$slot->end_time}");

        if ($shiftStart->gt($now)) {
            return false;
        }

        // Kedatangan dan kepulangan diundi terpisah agar polanya realistis.
        $arrival = mt_rand(1, 100);
        $departure = mt_rand(1, 100);
        $running = $shiftEnd->gt($now);

        if ($arrival <= 6) {
            if ($running) {
                return false; // belum datang; akan tercatat tidak hadir setelah shift berakhir
            }

            Attendance::query()->create([
                'shift_assignment_id' => $assignment->id,
                'staff_profile_id' => $assignment->staff_profile_id,
                'status' => AttendanceStatus::Absent,
            ]);

            return true;
        }

        $checkIn = $arrival <= 16
            ? $shiftStart->copy()->addMinutes(mt_rand(11, 25))   // terlambat
            : $shiftStart->copy()->subMinutes(mt_rand(0, 12));   // tepat waktu
        if ($checkIn->gt($now)) {
            return false;
        }

        $checkOut = match (true) {
            $running => null,                                                     // masih bertugas
            $departure <= 3 => null,                                              // lupa check-out
            $departure <= 10 => $shiftEnd->copy()->subMinutes(mt_rand(15, 40)),   // pulang lebih cepat
            default => $shiftEnd->copy()->addMinutes(mt_rand(0, 10)),
        };

        Attendance::query()->create([
            'shift_assignment_id' => $assignment->id,
            'staff_profile_id' => $assignment->staff_profile_id,
            'check_in_at' => $checkIn,
            'check_out_at' => $checkOut,
            'check_in_method' => AttendanceMethod::Qr,
            'check_out_method' => $checkOut ? AttendanceMethod::Qr : null,
            'status' => $checkIn->gt($shiftStart->copy()->addMinutes($tolerance)) ? AttendanceStatus::Late : AttendanceStatus::OnTime,
        ]);

        return true;
    }

    /*
    |--------------------------------------------------------------------------
    | Penggajian
    |--------------------------------------------------------------------------
    */

    /** Periode gaji bulan berjalan (draft) yang langsung dihitung dari presensi di atas. */
    private function seedPayroll(PayrollService $payroll, Carbon $now): void
    {
        $start = $now->copy()->startOfMonth();
        $end = $now->copy()->endOfMonth();

        $overlap = PayrollPeriod::query()
            ->where('start_date', '<=', $end->toDateString())
            ->where('end_date', '>=', $start->toDateString())
            ->exists();
        if ($overlap) {
            $this->command?->warn('Periode gaji bulan ini dilewati: sudah ada periode gaji pada bulan berjalan.');

            return;
        }

        $period = $payroll->calculate(PayrollPeriod::query()->create([
            'start_date' => $start->toDateString(),
            'end_date' => $end->toDateString(),
            'status' => PayrollStatus::Draft,
        ]));

        $this->command?->info(sprintf(
            'Periode gaji %s (draft): %d staff, total Rp%s.',
            $start->translatedFormat('F Y'),
            $period->details()->count(),
            number_format((float) $period->total_amount, 0, ',', '.'),
        ));
    }
}
