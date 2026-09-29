<?php

namespace Tests\Unit;

use App\Services\Payroll\PayrollCalculator;
use App\Support\Time;
use PHPUnit\Framework\TestCase;

/**
 * Pengujian perhitungan gaji per shift tanpa database.
 * Tarif contoh: 08–16 Rp11.000/jam, 16–17 Rp13.500/jam (sama dengan seeder versi Agustus 2026).
 */
class PayrollCalculatorTest extends TestCase
{
    private function m(string $time): int
    {
        return Time::toMinutes($time);
    }

    private function rates(): array
    {
        return array_map(fn (int $hour) => [
            'start' => $hour * 60,
            'end' => ($hour + 1) * 60,
            'rate' => $hour >= 16 ? 13500 : 11000,
        ], range(8, 16));
    }

    private function calculate(string $shiftStart, string $shiftEnd, ?string $in, ?string $out, int $unit = 1, string $mode = 'down', ?array $rates = null): array
    {
        return (new PayrollCalculator($unit, $mode))->calculate(
            $this->m($shiftStart),
            $this->m($shiftEnd),
            $in ? $this->m($in) : null,
            $out ? $this->m($out) : null,
            $rates ?? $this->rates(),
        );
    }

    public function test_hadir_penuh_dikali_tarif_per_jam(): void
    {
        $line = $this->calculate('08:00', '10:00', '08:00', '10:00');

        $this->assertSame(120, $line['actual_minutes']);
        $this->assertSame(120, $line['paid_minutes']);
        $this->assertSame(22000.0, $line['amount']);
        $this->assertCount(2, $line['breakdown']);
    }

    public function test_tarif_berbeda_per_slot_jam(): void
    {
        // 15:00–17:00: 60 menit × Rp11.000 + 60 menit × Rp13.500
        $line = $this->calculate('15:00', '17:00', '15:00', '17:00');

        $this->assertSame(24500.0, $line['amount']);
        $this->assertSame([11000, 13500], array_column($line['breakdown'], 'rate'));
    }

    public function test_jam_hadir_dibatasi_di_dalam_jam_shift(): void
    {
        // Datang 15:40 & pulang 17:25 untuk shift 16:00–17:00 → dibayar 60 menit saja.
        $line = $this->calculate('16:00', '17:00', '15:40', '17:25');

        $this->assertSame(60, $line['actual_minutes']);
        $this->assertSame(13500.0, $line['amount']);
    }

    public function test_pembulatan_per_30_menit_ke_bawah(): void
    {
        // Hadir 08:10–09:50 = 100 menit → 90 menit (08:10–09:40): 50 mnt × 11.000 + 40 mnt × 11.000
        $line = $this->calculate('08:00', '10:00', '08:10', '09:50', unit: 30, mode: 'down');

        $this->assertSame(100, $line['actual_minutes']);
        $this->assertSame(90, $line['paid_minutes']);
        $this->assertSame(
            [['08:10', '09:00', 50], ['09:00', '09:40', 40]],
            array_map(fn ($b) => [$b['from'], $b['to'], $b['minutes']], $line['breakdown']),
        );
        $this->assertSame(16500.0, $line['amount']);
    }

    public function test_pembulatan_per_menit_tidak_mengubah_durasi(): void
    {
        $line = $this->calculate('08:00', '10:00', '08:10', '09:50', unit: 1);

        $this->assertSame(100, $line['paid_minutes']);
        // 100 menit × Rp11.000 ÷ 60 = 18.333,33 (dibulatkan sekali dari nilai eksak)
        $this->assertSame(18333.33, $line['amount']);
    }

    public function test_pembulatan_ke_terdekat_tetap_di_dalam_jam_shift(): void
    {
        // 110 menit dibulatkan ke 120 menit; rentang yang dibayar digeser agar tetap 08:00–10:00.
        $line = $this->calculate('08:00', '10:00', '08:10', '10:00', unit: 30, mode: 'nearest');

        $this->assertSame(110, $line['actual_minutes']);
        $this->assertSame(120, $line['paid_minutes']);
        $this->assertSame('08:00', $line['breakdown'][0]['from']);
        $this->assertSame(22000.0, $line['amount']);
    }

    public function test_pembulatan_ke_bawah_bisa_membuat_nol(): void
    {
        $line = $this->calculate('08:00', '10:00', '09:35', '10:00', unit: 30, mode: 'down');

        $this->assertSame(25, $line['actual_minutes']);
        $this->assertSame(0, $line['paid_minutes']);
        $this->assertSame(0.0, $line['amount']);
    }

    public function test_terlambat_mengurangi_jam_yang_dibayar(): void
    {
        $line = $this->calculate('10:00', '12:00', '10:45', '12:00');

        $this->assertSame(75, $line['paid_minutes']);
        $this->assertSame(13750.0, $line['amount']);
    }

    public function test_tidak_hadir_atau_belum_check_out_tidak_dibayar(): void
    {
        $this->assertSame(0.0, $this->calculate('08:00', '10:00', null, null)['amount']);
        $this->assertSame(0.0, $this->calculate('08:00', '10:00', '08:00', null)['amount']);
    }

    public function test_jam_tanpa_tarif_tidak_dibayar_dan_dicatat(): void
    {
        // Tarif hanya tersedia untuk 08–09.
        $line = $this->calculate('08:00', '10:00', '08:00', '10:00', rates: [['start' => 480, 'end' => 540, 'rate' => 10000]]);

        $this->assertSame(10000.0, $line['amount']);
        $this->assertSame(60, $line['unpaid_minutes']);
    }

    public function test_fungsi_pembulatan(): void
    {
        $this->assertSame(90, PayrollCalculator::roundMinutes(100, 30, 'down'));
        $this->assertSame(90, PayrollCalculator::roundMinutes(100, 30, 'nearest'));
        $this->assertSame(120, PayrollCalculator::roundMinutes(105, 30, 'nearest'));
        $this->assertSame(45, PayrollCalculator::roundMinutes(47, 15, 'down'));
        $this->assertSame(47, PayrollCalculator::roundMinutes(47, 1, 'down'));
    }
}
