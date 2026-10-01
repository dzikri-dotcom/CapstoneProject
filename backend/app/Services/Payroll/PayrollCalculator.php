<?php

namespace App\Services\Payroll;

use App\Support\Time;

/**
 * PERHITUNGAN GAJI PER SHIFT (tanpa akses database, mudah diuji).
 *
 *   Total Gaji = Σ (durasi hadir yang dibayar pada setiap slot jam × tarif slot jam tersebut)
 *
 * Langkah untuk satu presensi:
 *  1. Jam hadir aktual = [check-in, check-out] dibatasi di dalam jam shift
 *     (datang lebih awal / pulang lebih lambat tidak dihitung).
 *  2. Durasi dibulatkan sesuai pengaturan (satuan 1/5/15/30/60 menit; ke bawah atau terdekat),
 *     maksimal sepanjang durasi shift.
 *  3. Rentang yang dibayar dimulai dari jam mulai hadir; selisih pembulatan
 *     dipotong/ditambahkan di bagian akhir (tetap di dalam jam shift).
 *  4. Rentang tersebut dipecah per slot jam tarif (08–09, 09–10, …) lalu
 *     setiap potongan dikalikan tarif slot jamnya: menit × tarif ÷ 60.
 * Tidak hadir atau belum check-out → tidak dibayar (0) sampai dikoreksi admin.
 *
 * Tanpa potongan gaji (pengaturan bawaan): staff yang hadir dibayar penuh sepanjang jam shift,
 * lihat fullShift(). Terlambat / pulang lebih cepat tidak mengurangi gaji.
 */
final class PayrollCalculator
{
    public function __construct(
        private readonly int $roundingUnit = 1,
        private readonly string $roundingMode = 'down',
    ) {
    }

    /**
     * Pembulatan durasi: 'down' = ke bawah, 'nearest' = ke kelipatan terdekat.
     * Aturan yang sama dipakai pratinjau di halaman Pengaturan.
     */
    public static function roundMinutes(int $minutes, int $unit, string $mode): int
    {
        if ($unit <= 1) {
            return $minutes;
        }

        return $mode === 'nearest'
            ? (int) round($minutes / $unit) * $unit
            : intdiv($minutes, $unit) * $unit;
    }

    /**
     * Tanpa potongan: seluruh jam shift dibayar asalkan staff check-in.
     * `actual_minutes` tetap mencatat menit hadir sebenarnya sebagai informasi.
     *
     * @param  list<array{start:int, end:int, rate:int}>  $rates
     * @return array{actual_minutes:int, paid_minutes:int, amount:float, breakdown:list<array<string, mixed>>, unpaid_minutes:int}
     */
    public function fullShift(int $shiftStart, int $shiftEnd, ?int $checkIn, ?int $checkOut, array $rates): array
    {
        if ($checkIn === null) {
            return $this->calculate($shiftStart, $shiftEnd, null, null, $rates);
        }

        $actual = $checkOut === null ? 0 : max(0, min($checkOut, $shiftEnd) - max($checkIn, $shiftStart));
        $full = (new self(1, 'down'))->calculate($shiftStart, $shiftEnd, $shiftStart, $shiftEnd, $rates);

        return ['actual_minutes' => $actual] + $full;
    }

    /**
     * @param  int  $shiftStart  menit sejak 00:00
     * @param  int  $shiftEnd  menit sejak 00:00
     * @param  ?int  $checkIn  menit sejak 00:00 pada tanggal shift (null = tidak check-in)
     * @param  ?int  $checkOut  menit sejak 00:00 pada tanggal shift (null = belum check-out)
     * @param  list<array{start:int, end:int, rate:int}>  $rates  tarif per slot jam yang berlaku pada tanggal shift
     * @return array{actual_minutes:int, paid_minutes:int, amount:float, breakdown:list<array<string, mixed>>, unpaid_minutes:int}
     */
    public function calculate(int $shiftStart, int $shiftEnd, ?int $checkIn, ?int $checkOut, array $rates): array
    {
        $empty = ['actual_minutes' => 0, 'paid_minutes' => 0, 'amount' => 0.0, 'breakdown' => [], 'unpaid_minutes' => 0];

        if ($checkIn === null || $checkOut === null) {
            return $empty;
        }

        // 1. Jam hadir aktual dibatasi di dalam jam shift.
        $start = max($checkIn, $shiftStart);
        $end = min($checkOut, $shiftEnd);
        $actual = max(0, $end - $start);

        // 2. Pembulatan (tidak boleh melebihi durasi shift).
        $paid = min(self::roundMinutes($actual, $this->roundingUnit, $this->roundingMode), $shiftEnd - $shiftStart);
        if ($paid <= 0) {
            return ['actual_minutes' => $actual] + $empty;
        }

        // 3. Rentang yang dibayar, mulai dari jam mulai hadir.
        $paidStart = $start;
        $paidEnd = $start + $paid;
        if ($paidEnd > $shiftEnd) {
            $paidEnd = $shiftEnd;
            $paidStart = $shiftEnd - $paid;
        }

        // 4. Pecah per slot jam tarif dan kalikan tarifnya.
        $breakdown = [];
        $covered = 0;
        $exactAmount = 0.0;
        foreach ($rates as $rate) {
            $from = max($paidStart, $rate['start']);
            $to = min($paidEnd, $rate['end']);
            if ($to <= $from) {
                continue;
            }

            $minutes = $to - $from;
            $covered += $minutes;
            $exactAmount += $minutes * $rate['rate'] / 60;
            $breakdown[] = [
                'from' => Time::format($from),
                'to' => Time::format($to),
                'minutes' => $minutes,
                'rate' => $rate['rate'],
                'amount' => round($minutes * $rate['rate'] / 60, 2),
            ];
        }

        return [
            'actual_minutes' => $actual,
            'paid_minutes' => $paid,
            // Dijumlahkan dari nilai eksak lalu dibulatkan sekali (bukan jumlah potongan yang sudah dibulatkan).
            'amount' => round($exactAmount, 2),
            'breakdown' => $breakdown,
            // Menit yang jatuh di jam tanpa tarif (tarif belum diisi admin) → tidak dibayar.
            'unpaid_minutes' => $paid - $covered,
        ];
    }
}
