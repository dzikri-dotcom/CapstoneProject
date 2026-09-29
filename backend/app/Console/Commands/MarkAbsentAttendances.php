<?php

namespace App\Console\Commands;

use App\Services\Attendance\AttendanceService;
use Illuminate\Console\Command;

class MarkAbsentAttendances extends Command
{
    protected $signature = 'attendance:mark-absent';

    protected $description = 'Tandai shift yang sudah berakhir tanpa check-in sebagai tidak hadir';

    public function handle(AttendanceService $attendance): int
    {
        $count = $attendance->markAbsent();
        $this->info("{$count} penugasan shift ditandai tidak hadir.");

        return self::SUCCESS;
    }
}
