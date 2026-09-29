<?php

namespace Database\Seeders;

use App\Enums\AttendanceMethod;
use App\Enums\AttendanceStatus;
use App\Enums\SchedulePeriodStatus;
use App\Models\Attendance;
use App\Models\SchedulePeriod;
use App\Models\ShiftAssignment;
use App\Services\Scheduling\ScheduleGenerator;
use App\Services\SettingService;
use Carbon\Carbon;
use Illuminate\Database\Seeder;

/**
 * DATA DEMO (opsional): jadwal 2 minggu terakhir + presensi simulasi,
 * agar fitur penggajian dan laporan bisa langsung dicoba.
 *
 *   php artisan db:seed --class=DemoDataSeeder
 *
 * - Jadwal dibuat dengan algoritma Round Robin (pointer berlanjut antar minggu) lalu dipublikasikan.
 * - Presensi disimulasikan dengan pola tetap (acak ber-seed): ±80% tepat waktu, ±10% terlambat,
 *   sebagian pulang lebih cepat, ±3% lupa check-out, ±6% tidak hadir.
 */
class DemoDataSeeder extends Seeder
{
    public function run(ScheduleGenerator $generator, SettingService $settings): void
    {
        $weekStart = now()->startOfWeek(Carbon::MONDAY)->subWeeks(2);

        if (SchedulePeriod::query()->where('start_date', '<', now()->startOfWeek(Carbon::MONDAY)->toDateString())
            ->where('end_date', '>=', $weekStart->toDateString())->exists()) {
            $this->command?->warn('Data demo dilewati: sudah ada periode jadwal pada 2 minggu terakhir.');

            return;
        }

        mt_srand(2026);
        $tolerance = $settings->get('late_tolerance_minutes');

        for ($week = 0; $week < 2; $week++) {
            $start = $weekStart->copy()->addWeeks($week);
            $period = SchedulePeriod::query()->create([
                'start_date' => $start->toDateString(),
                'end_date' => $start->copy()->addDays(6)->toDateString(),
                'status' => SchedulePeriodStatus::Draft,
            ]);

            $generator->generate($period);
            $period->update(['status' => SchedulePeriodStatus::Published, 'published_at' => $start->copy()->subDays(2)->setTime(15, 0)]);

            $assignments = ShiftAssignment::query()
                ->whereHas('shiftSlot', fn ($q) => $q->where('schedule_period_id', $period->id))
                ->with('shiftSlot')
                ->orderBy('id')
                ->get();

            foreach ($assignments as $assignment) {
                $this->simulateAttendance($assignment, $tolerance);
            }

            $this->command?->info(sprintf(
                'Periode %s – %s: %d penugasan dengan presensi simulasi.',
                $period->start_date->toDateString(),
                $period->end_date->toDateString(),
                $assignments->count(),
            ));
        }
    }

    private function simulateAttendance(ShiftAssignment $assignment, int $tolerance): void
    {
        $slot = $assignment->shiftSlot;
        $date = $slot->date->toDateString();
        $shiftStart = Carbon::parse("{$date} {$slot->start_time}");
        $shiftEnd = Carbon::parse("{$date} {$slot->end_time}");
        // Kedatangan dan kepulangan diundi terpisah agar polanya realistis.
        $arrival = mt_rand(1, 100);
        $departure = mt_rand(1, 100);

        if ($arrival <= 6) {
            Attendance::query()->create([
                'shift_assignment_id' => $assignment->id,
                'staff_profile_id' => $assignment->staff_profile_id,
                'status' => AttendanceStatus::Absent,
            ]);

            return;
        }

        $checkIn = $arrival <= 16
            ? $shiftStart->copy()->addMinutes(mt_rand(11, 25))   // terlambat
            : $shiftStart->copy()->subMinutes(mt_rand(0, 12));   // tepat waktu

        $checkOut = match (true) {
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
    }
}
