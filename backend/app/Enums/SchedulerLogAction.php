<?php

namespace App\Enums;

/**
 * Jenis langkah yang dicatat algoritma Round Robin di tabel scheduler_logs.
 */
enum SchedulerLogAction: string
{
    case Queue = 'queue';
    case Assigned = 'assigned';
    case Skipped = 'skipped';
    case Understaffed = 'understaffed';

    public function label(): string
    {
        return match ($this) {
            self::Queue => 'Antrian awal',
            self::Assigned => 'Ditugaskan',
            self::Skipped => 'Dilewati',
            self::Understaffed => 'Kurang staff',
        };
    }
}
