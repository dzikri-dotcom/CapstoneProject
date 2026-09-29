<?php

namespace App\Services\Payroll;

use App\Enums\PayrollStatus;
use App\Models\HourlyRate;
use App\Models\PayrollItem;
use App\Models\PayrollPeriod;
use App\Models\ShiftAssignment;
use App\Models\StaffProfile;
use App\Models\User;
use App\Services\Attendance\AttendanceService;
use App\Services\SettingService;
use App\Support\Time;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Memproses penggajian satu periode: mengambil presensi dari jadwal yang dipublikasikan,
 * menghitung setiap shift dengan PayrollCalculator memakai tarif yang berlaku pada
 * tanggal shift tersebut, lalu menyimpan rekap per staff dan rincian per tanggal.
 */
class PayrollService
{
    public function __construct(
        private readonly SettingService $settings,
        private readonly AttendanceService $attendance,
    ) {
    }

    public function calculate(PayrollPeriod $period): PayrollPeriod
    {
        $this->ensureDraft($period);

        // Shift yang sudah lewat tanpa check-in dicatat tidak hadir terlebih dahulu.
        $this->attendance->markAbsent();

        $settings = $this->settings->all();
        $calculator = new PayrollCalculator($settings['rounding_unit_minutes'], $settings['rounding_mode']);

        $byStaff = $this->attendance->publishedAssignments()
            ->whereHas('shiftSlot', fn (Builder $slot) => $slot->whereBetween('date', [
                $period->start_date->toDateString(),
                $period->end_date->toDateString(),
            ]))
            ->whereHas('attendance') // hanya shift yang sudah berjalan
            ->with(['shiftSlot', 'attendance'])
            ->get()
            ->sortBy(fn (ShiftAssignment $a) => $a->shiftSlot->date->toDateString().' '.$a->shiftSlot->start_time)
            ->groupBy('staff_profile_id');

        $ratesByDate = [];

        DB::transaction(function () use ($period, $byStaff, $calculator, &$ratesByDate, $settings) {
            $period->details()->delete(); // rincian lama ikut terhapus (cascade)
            $now = now();
            $periodTotal = 0.0;

            foreach ($byStaff as $staffId => $assignments) {
                $detail = $period->details()->create(['staff_profile_id' => $staffId]);
                $items = [];
                $shifts = 0;
                $minutes = 0;
                $amount = 0.0;

                foreach ($assignments as $assignment) {
                    $slot = $assignment->shiftSlot;
                    $record = $assignment->attendance;
                    $date = $slot->date->toDateString();
                    $ratesByDate[$date] ??= $this->ratesOn($date);

                    $line = $calculator->calculate(
                        Time::toMinutes($slot->start_time),
                        Time::toMinutes($slot->end_time),
                        $this->minutesOnDate($record->check_in_at, $slot->date),
                        $this->minutesOnDate($record->check_out_at, $slot->date),
                        $ratesByDate[$date],
                    );

                    $items[] = [
                        'payroll_detail_id' => $detail->id,
                        'attendance_id' => $record->id,
                        'date' => $date,
                        'shift_start' => $slot->start_time,
                        'shift_end' => $slot->end_time,
                        'check_in_at' => $record->check_in_at,
                        'check_out_at' => $record->check_out_at,
                        'actual_minutes' => $line['actual_minutes'],
                        'paid_minutes' => $line['paid_minutes'],
                        'amount' => $line['amount'],
                        'breakdown' => json_encode([
                            'segments' => $line['breakdown'],
                            'unpaid_minutes' => $line['unpaid_minutes'],
                            'status' => $record->status->value,
                        ]),
                        'created_at' => $now,
                        'updated_at' => $now,
                    ];

                    $shifts += $record->check_in_at ? 1 : 0;
                    $minutes += $line['paid_minutes'];
                    $amount += $line['amount'];
                }

                PayrollItem::query()->insert($items);
                $detail->update(['total_shifts' => $shifts, 'total_minutes' => $minutes, 'total_amount' => round($amount, 2)]);
                $periodTotal += $amount;
            }

            $period->update([
                'total_amount' => round($periodTotal, 2),
                'calculated_at' => $now,
                'settings_snapshot' => [
                    'rounding_unit_minutes' => $settings['rounding_unit_minutes'],
                    'rounding_mode' => $settings['rounding_mode'],
                ],
            ]);
        });

        return $period->refresh();
    }

    /**
     * Finalisasi: hitung ulang (agar koreksi presensi terakhir ikut masuk), lalu kunci.
     * Setelah final, gaji, presensi, dan tarif pada periode ini tidak bisa diubah lagi.
     */
    public function finalize(PayrollPeriod $period, User $admin): PayrollPeriod
    {
        $this->calculate($period);

        $period->update([
            'status' => PayrollStatus::Final,
            'finalized_at' => now(),
            'finalized_by' => $admin->id,
        ]);

        return $period;
    }

    /**
     * Estimasi gaji seorang staff pada rentang tanggal (misal bulan berjalan) dengan aturan
     * yang sama seperti penggajian resmi. Hanya shift yang sudah berjalan yang dihitung.
     *
     * @return array{shifts:int, paid_minutes:int, amount:float}
     */
    public function estimate(StaffProfile $staff, Carbon $from, Carbon $to): array
    {
        $settings = $this->settings->all();
        $calculator = new PayrollCalculator($settings['rounding_unit_minutes'], $settings['rounding_mode']);
        $ratesByDate = [];
        $result = ['shifts' => 0, 'paid_minutes' => 0, 'amount' => 0.0];

        $assignments = $this->attendance->publishedAssignments()
            ->where('staff_profile_id', $staff->id)
            ->whereHas('shiftSlot', fn (Builder $slot) => $slot->whereBetween('date', [$from->toDateString(), $to->toDateString()]))
            ->whereHas('attendance', fn (Builder $q) => $q->whereNotNull('check_in_at'))
            ->with(['shiftSlot', 'attendance'])
            ->get();

        foreach ($assignments as $assignment) {
            $slot = $assignment->shiftSlot;
            $date = $slot->date->toDateString();
            $ratesByDate[$date] ??= $this->ratesOn($date);
            $line = $calculator->calculate(
                Time::toMinutes($slot->start_time),
                Time::toMinutes($slot->end_time),
                $this->minutesOnDate($assignment->attendance->check_in_at, $slot->date),
                $this->minutesOnDate($assignment->attendance->check_out_at, $slot->date),
                $ratesByDate[$date],
            );
            $result['shifts']++;
            $result['paid_minutes'] += $line['paid_minutes'];
            $result['amount'] += $line['amount'];
        }

        $result['amount'] = round($result['amount'], 2);

        return $result;
    }

    public function delete(PayrollPeriod $period): void
    {
        $this->ensureDraft($period);
        $period->delete();
    }

    /**
     * @return list<array{start:int, end:int, rate:int}>
     */
    private function ratesOn(string $date): array
    {
        return HourlyRate::effectiveOn($date)->get()->map(fn (HourlyRate $rate) => [
            'start' => Time::toMinutes($rate->start_time),
            'end' => Time::toMinutes($rate->end_time),
            'rate' => $rate->rate,
        ])->all();
    }

    /** Jam presensi → menit sejak 00:00 pada tanggal shift. */
    private function minutesOnDate(?Carbon $moment, Carbon $date): ?int
    {
        return $moment ? (int) $date->copy()->startOfDay()->diffInMinutes($moment, false) : null;
    }

    private function ensureDraft(PayrollPeriod $period): void
    {
        if ($period->isFinal()) {
            throw ValidationException::withMessages([
                'period' => 'Periode gaji ini sudah final sehingga tidak bisa diubah.',
            ]);
        }
    }
}
