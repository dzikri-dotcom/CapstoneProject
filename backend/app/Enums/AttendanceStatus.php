<?php

namespace App\Enums;

enum AttendanceStatus: string
{
    case OnTime = 'on_time';
    case Late = 'late';
    case Absent = 'absent';

    public function label(): string
    {
        return match ($this) {
            self::OnTime => 'Tepat waktu',
            self::Late => 'Terlambat',
            self::Absent => 'Tidak hadir',
        };
    }
}
