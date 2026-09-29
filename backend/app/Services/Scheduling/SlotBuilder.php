<?php

namespace App\Services\Scheduling;

use App\Support\Time;
use Carbon\CarbonInterface;
use Carbon\CarbonPeriod;

/**
 * Langkah 2 algoritma: membentuk daftar slot shift dari jam operasional dan durasi shift.
 *
 * Contoh jam operasional 08:00–17:00 dengan durasi 120 menit:
 *   08–10, 10–12, 12–14, 14–16, 16–17 (slot terakhir dipendekkan sampai jam tutup).
 */
final class SlotBuilder
{
    /**
     * @param  array<int, array{open:int, close:int}>  $hoursByDay  hari aktif (1=Senin..7=Minggu) → menit buka/tutup
     * @return list<array{key:string, date:string, day_of_week:int, start:int, end:int, required:int}>
     */
    public static function build(
        CarbonInterface $from,
        CarbonInterface $to,
        array $hoursByDay,
        int $durationMinutes,
        int $requiredStaff,
    ): array {
        $slots = [];

        foreach (CarbonPeriod::create($from->copy()->startOfDay(), $to->copy()->startOfDay()) as $date) {
            $day = $date->dayOfWeekIso;
            if (! isset($hoursByDay[$day])) {
                continue; // hari libur / nonaktif
            }

            ['open' => $open, 'close' => $close] = $hoursByDay[$day];

            for ($start = $open; $start < $close; $start += $durationMinutes) {
                $slots[] = [
                    'key' => $date->toDateString().' '.Time::format($start),
                    'date' => $date->toDateString(),
                    'day_of_week' => $day,
                    'start' => $start,
                    'end' => min($start + $durationMinutes, $close),
                    'required' => $requiredStaff,
                ];
            }
        }

        return $slots;
    }
}
