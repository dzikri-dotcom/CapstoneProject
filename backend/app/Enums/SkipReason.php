<?php

namespace App\Enums;

/**
 * Alasan algoritma Round Robin melewati staff yang sedang ditunjuk pointer.
 */
enum SkipReason: string
{
    case AlreadyAssigned = 'already_assigned';
    case ClassConflict = 'class_conflict';
    case MaxWeeklyHours = 'max_weekly_hours';
    case ShiftConflict = 'shift_conflict';

    public function label(): string
    {
        return match ($this) {
            self::AlreadyAssigned => 'Sudah ditugaskan di slot ini',
            self::ClassConflict => 'Bentrok jadwal kuliah',
            self::MaxWeeklyHours => 'Melebihi batas jam per minggu',
            self::ShiftConflict => 'Sudah bertugas di pos lain pada jam yang sama',
        };
    }
}
