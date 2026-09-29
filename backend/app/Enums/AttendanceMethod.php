<?php

namespace App\Enums;

/**
 * Cara check-in/check-out dicatat: scan QR oleh staff atau koreksi manual oleh admin.
 */
enum AttendanceMethod: string
{
    case Qr = 'qr';
    case Manual = 'manual';

    public function label(): string
    {
        return match ($this) {
            self::Qr => 'Scan QR',
            self::Manual => 'Koreksi admin',
        };
    }
}
