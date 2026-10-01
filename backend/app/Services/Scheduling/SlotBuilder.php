<?php

namespace App\Services\Scheduling;

use App\Support\Time;
use Carbon\CarbonInterface;
use Carbon\CarbonPeriod;

/**
 * Langkah 2 algoritma: membentuk daftar slot dari hari operasional × shift × pos jaga.
 *
 * Contoh 2 shift (08:00–12:00, 13:00–17:00) dan 3 pos berkapasitas 2:
 *   setiap hari aktif menghasilkan 6 slot, masing-masing butuh 2 staff.
 * Shift dipotong ke jam operasional hari itu; shift yang seluruhnya di luar jam buka dilewati.
 */
final class SlotBuilder
{
    /**
     * @param  array<int, array{open:int, close:int}>  $hoursByDay  hari aktif (1=Senin..7=Minggu) → menit buka/tutup
     * @param  list<array{id:?int, name:string, start:int, end:int}>  $shifts  shift aktif
     * @param  list<array{id:?int, name:string, capacity:int, location?:?string}>  $posts  pos jaga aktif
     * @return list<array{key:string, date:string, day_of_week:int, start:int, end:int, required:int, shift_id:?int, shift_name:string, post_id:?int, post_name:string}>
     */
    public static function build(CarbonInterface $from, CarbonInterface $to, array $hoursByDay, array $shifts, array $posts): array
    {
        usort($shifts, fn (array $a, array $b) => [$a['start'], $a['end']] <=> [$b['start'], $b['end']]);

        $slots = [];

        foreach (CarbonPeriod::create($from->copy()->startOfDay(), $to->copy()->startOfDay()) as $date) {
            $day = $date->dayOfWeekIso;
            if (! isset($hoursByDay[$day])) {
                continue; // hari libur / nonaktif
            }

            ['open' => $open, 'close' => $close] = $hoursByDay[$day];

            foreach ($shifts as $shift) {
                $start = max($shift['start'], $open);
                $end = min($shift['end'], $close);
                if ($start >= $end) {
                    continue; // shift di luar jam operasional hari ini
                }

                foreach ($posts as $index => $post) {
                    $slots[] = [
                        'key' => sprintf('%s %s-%s #%s', $date->toDateString(), Time::format($start), Time::format($end), $post['id'] ?? $index),
                        'date' => $date->toDateString(),
                        'day_of_week' => $day,
                        'start' => $start,
                        'end' => $end,
                        'required' => $post['capacity'],
                        'shift_id' => $shift['id'] ?? null,
                        'shift_name' => $shift['name'],
                        'post_id' => $post['id'] ?? null,
                        'post_name' => $post['name'],
                        'post_location' => $post['location'] ?? null,
                    ];
                }
            }
        }

        return $slots;
    }
}
