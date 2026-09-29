<?php

namespace App\Enums;

/**
 * Asal penugasan shift: hasil algoritma Round Robin atau diisi/diedit admin.
 */
enum AssignmentSource: string
{
    case Auto = 'auto';
    case Manual = 'manual';

    public function label(): string
    {
        return match ($this) {
            self::Auto => 'Otomatis',
            self::Manual => 'Manual',
        };
    }
}
