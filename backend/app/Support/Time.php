<?php

namespace App\Support;

/**
 * Konversi jam "HH:MM" ⇄ menit sejak 00:00, agar perbandingan & perhitungan
 * rentang waktu (jadwal kuliah, slot shift, presensi) cukup memakai bilangan bulat.
 */
final class Time
{
    /** "08:30" atau "08:30:00" → 510 */
    public static function toMinutes(string $time): int
    {
        [$hours, $minutes] = explode(':', $time);

        return (int) $hours * 60 + (int) $minutes;
    }

    /** 510 → "08:30" */
    public static function format(int $minutes): string
    {
        return sprintf('%02d:%02d', intdiv($minutes, 60), $minutes % 60);
    }

    /** 390 → "6 jam 30 menit", 120 → "2 jam", 45 → "45 menit" */
    public static function duration(int $minutes): string
    {
        $hours = intdiv($minutes, 60);
        $rest = $minutes % 60;

        return trim(($hours ? "{$hours} jam " : '').($rest || ! $hours ? "{$rest} menit" : ''));
    }

    /** Apakah rentang [aStart, aEnd) dan [bStart, bEnd) beririsan? Bersentuhan (10:00 & 10:00) tidak dihitung. */
    public static function overlaps(int $aStart, int $aEnd, int $bStart, int $bEnd): bool
    {
        return $aStart < $bEnd && $bStart < $aEnd;
    }
}
