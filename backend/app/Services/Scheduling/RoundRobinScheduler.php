<?php

namespace App\Services\Scheduling;

use App\Enums\SchedulerLogAction;
use App\Enums\SkipReason;
use App\Support\Time;
use Carbon\Carbon;
use Illuminate\Support\Str;

/**
 * ALGORITMA PENJADWALAN SHIFT ROUND ROBIN (inti penelitian).
 *
 * Kelas ini murni (tidak mengakses database) sehingga mudah diuji dan dijelaskan.
 * Data dibaca & disimpan oleh ScheduleGenerator.
 *
 * Alur:
 *  1. Staff aktif disusun dalam ANTRIAN MELINGKAR (circular queue) berurutan ID,
 *     diputar agar dimulai dari posisi pointer terakhir periode sebelumnya.
 *  2. Untuk setiap slot (urut tanggal & jam), cek staff yang ditunjuk pointer:
 *       - jika kosong di seluruh rentang slot (tidak ada kuliah yang beririsan),
 *         total jam minggu itu + durasi slot tidak melebihi batas, dan belum
 *         ditugaskan di slot itu → TUGASKAN;
 *       - jika tidak → LEWATI dengan alasan yang dicatat.
 *     Setelah dicek (ditugaskan maupun dilewati) pointer MAJU ke staff berikutnya.
 *  3. Ulangi sampai kebutuhan slot terpenuhi atau seluruh antrian sudah dicek
 *     satu putaran penuh. Jika masih kurang → slot ditandai KURANG STAFF.
 *  4. Pointer tidak kembali ke awal untuk slot berikutnya, sehingga giliran
 *     berputar adil; posisi pointer terakhir dikembalikan untuk periode berikutnya.
 */
final class RoundRobinScheduler
{
    public function __construct(private readonly int $maxWeeklyMinutes)
    {
    }

    /**
     * @param  list<array{key:string, date:string, day_of_week:int, start:int, end:int, required:int}>  $slots  urut waktu
     * @param  list<array{id:int, name:string, classes:array<int, list<array{start:int, end:int, course?:?string}>>}>  $staff  staff aktif urut ID
     * @param  ?int  $startStaffId  staff yang ditunjuk pointer di awal (null = staff pertama)
     * @param  array<int, array<string, int>>  $initialWeeklyMinutes  menit shift yang sudah dimiliki staff dari periode lain: [staffId][minggu ISO]
     */
    public function run(array $slots, array $staff, ?int $startStaffId = null, array $initialWeeklyMinutes = []): SchedulerResult
    {
        // Langkah 1: bentuk antrian melingkar mulai dari posisi pointer.
        $queue = $this->buildQueue($staff, $startStaffId);
        $queueSize = count($queue);
        $pointer = 0;

        $weeklyMinutes = $initialWeeklyMinutes;
        $minutesByStaff = array_fill_keys(array_column($queue, 'id'), 0);
        $assignments = [];
        $understaffed = [];
        $logs = [];
        $step = 0;

        $logs[] = $this->log(
            step: $step,
            action: SchedulerLogAction::Queue,
            message: 'Antrian awal: '.($queueSize ? implode(' → ', array_column($queue, 'name')) : '(tidak ada staff aktif)'),
            meta: ['queue' => array_column($queue, 'id'), 'names' => array_column($queue, 'name')],
        );

        // Langkah 2: proses setiap slot secara berurutan.
        foreach ($slots as $slot) {
            $slotMinutes = $slot['end'] - $slot['start'];
            $week = Carbon::parse($slot['date'])->format('o-W'); // minggu ISO (Senin–Minggu)
            $assigned = [];
            $checked = 0;

            // Langkah 3: cek staff yang ditunjuk pointer sampai kebutuhan terpenuhi
            // atau seluruh antrian sudah dicek satu putaran penuh.
            while (count($assigned) < $slot['required'] && $checked < $queueSize) {
                $candidate = $queue[$pointer];
                $currentMinutes = $weeklyMinutes[$candidate['id']][$week] ?? 0;

                [$reason, $message] = $this->evaluate($candidate, $slot, $assigned, $currentMinutes, $slotMinutes);

                if ($reason === null) {
                    $assigned[] = $candidate['id'];
                    $weeklyMinutes[$candidate['id']][$week] = $currentMinutes + $slotMinutes;
                    $minutesByStaff[$candidate['id']] += $slotMinutes;
                }

                $logs[] = $this->log(
                    step: ++$step,
                    slotKey: $slot['key'],
                    staffId: $candidate['id'],
                    pointerPosition: $pointer + 1,
                    action: $reason === null ? SchedulerLogAction::Assigned : SchedulerLogAction::Skipped,
                    reason: $reason,
                    message: $message,
                    meta: [
                        'staff_name' => $candidate['name'],
                        'week_minutes_before' => $currentMinutes,
                        'slot_minutes' => $slotMinutes,
                    ],
                );

                // Pointer maju ke staff berikutnya (melingkar), baik ditugaskan maupun dilewati.
                $pointer = ($pointer + 1) % $queueSize;
                $checked++;
            }

            $assignments[$slot['key']] = $assigned;

            // Langkah 4: satu putaran penuh belum cukup → tandai kurang staff.
            $missing = $slot['required'] - count($assigned);
            if ($missing > 0) {
                $understaffed[$slot['key']] = $missing;
                $logs[] = $this->log(
                    step: ++$step,
                    slotKey: $slot['key'],
                    action: SchedulerLogAction::Understaffed,
                    message: sprintf(
                        'Slot kurang %d staff setelah seluruh antrian (%d staff) dicek satu putaran. Perlu diisi manual.',
                        $missing,
                        $queueSize,
                    ),
                    meta: ['missing' => $missing, 'required' => $slot['required']],
                );
            }
        }

        return new SchedulerResult(
            assignments: $assignments,
            logs: $logs,
            understaffed: $understaffed,
            startStaffId: $queue[0]['id'] ?? null,
            endStaffId: $queueSize ? $queue[$pointer]['id'] : null,
            minutesByStaff: $minutesByStaff,
        );
    }

    /**
     * Putar antrian agar dimulai dari staff yang ditunjuk pointer. Jika staff itu sudah
     * tidak aktif, mulai dari staff aktif berikutnya (ID lebih besar, melingkar).
     */
    private function buildQueue(array $staff, ?int $startStaffId): array
    {
        usort($staff, fn (array $a, array $b) => $a['id'] <=> $b['id']);

        $startIndex = 0;
        if ($startStaffId !== null) {
            foreach ($staff as $index => $member) {
                if ($member['id'] >= $startStaffId) {
                    $startIndex = $index;
                    break;
                }
            }
        }

        return array_merge(array_slice($staff, $startIndex), array_slice($staff, 0, $startIndex));
    }

    /**
     * Syarat staff boleh ditugaskan di slot. Mengembalikan [alasan dilewati|null, pesan log].
     *
     * @return array{0: ?SkipReason, 1: string}
     */
    private function evaluate(array $candidate, array $slot, array $assigned, int $currentMinutes, int $slotMinutes): array
    {
        $name = $candidate['name'];

        // (a) Belum ditugaskan di slot ini. Dalam satu putaran setiap staff hanya dicek sekali,
        // sehingga syarat ini adalah pengaman sesuai aturan penjadwalan.
        if (in_array($candidate['id'], $assigned, true)) {
            return [SkipReason::AlreadyAssigned, "{$name} dilewati: sudah ditugaskan di slot ini."];
        }

        // (b) Kosong di SELURUH rentang jam slot (tidak ada kuliah yang beririsan).
        foreach ($candidate['classes'][$slot['day_of_week']] ?? [] as $class) {
            if (Time::overlaps($slot['start'], $slot['end'], $class['start'], $class['end'])) {
                return [SkipReason::ClassConflict, sprintf(
                    '%s dilewati: bentrok kuliah%s %s–%s.',
                    $name,
                    empty($class['course']) ? '' : " {$class['course']}",
                    Time::format($class['start']),
                    Time::format($class['end']),
                )];
            }
        }

        // (c) Total jam minggu ini setelah ditambah slot tidak melebihi batas.
        if ($currentMinutes + $slotMinutes > $this->maxWeeklyMinutes) {
            return [SkipReason::MaxWeeklyHours, sprintf(
                '%s dilewati: %s + %s melebihi batas %s per minggu.',
                $name,
                Time::duration($currentMinutes),
                Time::duration($slotMinutes),
                Time::duration($this->maxWeeklyMinutes),
            )];
        }

        return [null, sprintf('%s ditugaskan (total minggu ini %s).', $name, Time::duration($currentMinutes + $slotMinutes))];
    }

    private function log(
        int $step,
        SchedulerLogAction $action,
        string $message,
        ?string $slotKey = null,
        ?int $staffId = null,
        ?int $pointerPosition = null,
        ?SkipReason $reason = null,
        array $meta = [],
    ): array {
        return [
            'step' => $step,
            'slot_key' => $slotKey,
            'staff_id' => $staffId,
            'pointer_position' => $pointerPosition,
            'action' => $action,
            'reason' => $reason,
            'message' => Str::limit($message, 250),
            'meta' => $meta,
        ];
    }
}
