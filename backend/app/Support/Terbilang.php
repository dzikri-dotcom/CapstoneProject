<?php

namespace App\Support;

/**
 * Angka → kata dalam Bahasa Indonesia untuk slip gaji.
 * Terbilang::rupiah(24500) → "Dua puluh empat ribu lima ratus rupiah"
 */
final class Terbilang
{
    private const WORDS = ['', 'satu', 'dua', 'tiga', 'empat', 'lima', 'enam', 'tujuh', 'delapan', 'sembilan', 'sepuluh', 'sebelas'];

    public static function make(int $number): string
    {
        return trim(match (true) {
            $number < 12 => self::WORDS[$number],
            $number < 20 => self::make($number - 10).' belas',
            $number < 100 => self::make(intdiv($number, 10)).' puluh '.self::make($number % 10),
            $number < 200 => 'seratus '.self::make($number - 100),
            $number < 1000 => self::make(intdiv($number, 100)).' ratus '.self::make($number % 100),
            $number < 2000 => 'seribu '.self::make($number - 1000),
            $number < 1_000_000 => self::make(intdiv($number, 1000)).' ribu '.self::make($number % 1000),
            $number < 1_000_000_000 => self::make(intdiv($number, 1_000_000)).' juta '.self::make($number % 1_000_000),
            default => self::make(intdiv($number, 1_000_000_000)).' miliar '.self::make($number % 1_000_000_000),
        });
    }

    public static function rupiah(float $amount): string
    {
        $number = (int) round($amount);

        return $number === 0 ? 'Nol rupiah' : ucfirst(self::make($number)).' rupiah';
    }
}
