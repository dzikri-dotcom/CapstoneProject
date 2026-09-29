<?php

namespace App\Services\Scheduling;

/**
 * Hasil satu kali eksekusi RoundRobinScheduler.
 */
final class SchedulerResult
{
    /**
     * @param  array<string, list<int>>  $assignments  kunci slot → ID staff yang ditugaskan (urut ditugaskan)
     * @param  list<array<string, mixed>>  $logs  langkah demi langkah proses algoritma
     * @param  array<string, int>  $understaffed  kunci slot → jumlah staff yang masih kurang
     * @param  ?int  $startStaffId  staff pertama di antrian (posisi pointer awal)
     * @param  ?int  $endStaffId  staff yang ditunjuk pointer setelah slot terakhir (awal periode berikutnya)
     * @param  array<int, int>  $minutesByStaff  total menit shift per staff dalam periode ini
     */
    public function __construct(
        public readonly array $assignments,
        public readonly array $logs,
        public readonly array $understaffed,
        public readonly ?int $startStaffId,
        public readonly ?int $endStaffId,
        public readonly array $minutesByStaff,
    ) {
    }

    public function slotCount(): int
    {
        return count($this->assignments);
    }

    public function filledSlotCount(): int
    {
        return $this->slotCount() - count($this->understaffed);
    }
}
