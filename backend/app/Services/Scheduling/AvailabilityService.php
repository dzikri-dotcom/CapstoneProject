<?php

namespace App\Services\Scheduling;

use App\Enums\DayOfWeek;
use App\Models\ClassSchedule;
use App\Models\OperationalHour;
use App\Models\StaffProfile;
use App\Support\Time;
use Illuminate\Support\Collection;

/**
 * Menghitung jam kosong staff: jam operasional dikurangi jadwal kuliah.
 *
 * Semua rentang waktu dinyatakan dalam menit sejak 00:00 dan bersifat setengah-terbuka
 * [mulai, selesai), sehingga kuliah 08:00–10:00 TIDAK bentrok dengan shift 10:00–12:00.
 */
class AvailabilityService
{
    /**
     * Gabungkan rentang sibuk yang beririsan/bersebelahan, setelah dipotong ke jam operasional.
     *
     * @param  array<int, array{start:int, end:int}>  $busy
     * @return array<int, array{start:int, end:int}>
     */
    public static function mergeBusy(int $open, int $close, array $busy): array
    {
        $clipped = collect($busy)
            ->map(fn (array $b) => ['start' => max($open, $b['start']), 'end' => min($close, $b['end'])])
            ->filter(fn (array $b) => $b['start'] < $b['end'])
            ->sortBy('start')
            ->values();

        $merged = [];
        foreach ($clipped as $interval) {
            $last = array_key_last($merged);
            if ($last !== null && $interval['start'] <= $merged[$last]['end']) {
                $merged[$last]['end'] = max($merged[$last]['end'], $interval['end']);
            } else {
                $merged[] = $interval;
            }
        }

        return $merged;
    }

    /**
     * Rentang kosong = jam operasional yang tidak tertutup rentang sibuk.
     *
     * @param  array<int, array{start:int, end:int}>  $busy
     * @return array<int, array{start:int, end:int}>
     */
    public static function freeIntervals(int $open, int $close, array $busy): array
    {
        $free = [];
        $cursor = $open;

        foreach (self::mergeBusy($open, $close, $busy) as $interval) {
            if ($interval['start'] > $cursor) {
                $free[] = ['start' => $cursor, 'end' => $interval['start']];
            }
            $cursor = max($cursor, $interval['end']);
        }

        if ($cursor < $close) {
            $free[] = ['start' => $cursor, 'end' => $close];
        }

        return $free;
    }

    /**
     * Apakah staff kosong pada SELURUH rentang [start, end)? (tidak ada kuliah yang beririsan)
     *
     * @param  array<int, array{start:int, end:int}>  $classesOnDay
     */
    public static function isFree(array $classesOnDay, int $start, int $end): bool
    {
        foreach ($classesOnDay as $class) {
            if (Time::overlaps($start, $end, $class['start'], $class['end'])) {
                return false;
            }
        }

        return true;
    }

    /**
     * Rekap jam kosong per hari operasional untuk satu staff (dipakai halaman jadwal kuliah).
     */
    public function forStaff(StaffProfile $staff): array
    {
        $classesByDay = $staff->classSchedules()->get()->groupBy('day_of_week');

        $days = OperationalHour::active()->orderBy('day_of_week')->get()
            ->map(function (OperationalHour $hour) use ($classesByDay) {
                $open = Time::toMinutes($hour->open_time);
                $close = Time::toMinutes($hour->close_time);
                $busy = $this->toIntervals($classesByDay->get($hour->day_of_week, collect()));

                $merged = self::mergeBusy($open, $close, $busy);
                $free = self::freeIntervals($open, $close, $busy);

                return [
                    'day_of_week' => $hour->day_of_week,
                    'day_label' => DayOfWeek::from($hour->day_of_week)->label(),
                    'open_time' => Time::format($open),
                    'close_time' => Time::format($close),
                    'operational_minutes' => $close - $open,
                    'free_minutes' => array_sum(array_map(fn ($f) => $f['end'] - $f['start'], $free)),
                    'busy' => $this->formatIntervals($merged),
                    'free' => $this->formatIntervals($free),
                ];
            });

        return [
            'days' => $days->values()->all(),
            'total_operational_minutes' => $days->sum('operational_minutes'),
            'total_free_minutes' => $days->sum('free_minutes'),
        ];
    }

    /**
     * @param  Collection<int, ClassSchedule>  $classes
     * @return array<int, array{start:int, end:int}>
     */
    private function toIntervals(Collection $classes): array
    {
        return $classes->map(fn (ClassSchedule $class) => [
            'start' => Time::toMinutes($class->start_time),
            'end' => Time::toMinutes($class->end_time),
        ])->all();
    }

    private function formatIntervals(array $intervals): array
    {
        return array_map(fn (array $i) => ['start' => Time::format($i['start']), 'end' => Time::format($i['end'])], $intervals);
    }
}
