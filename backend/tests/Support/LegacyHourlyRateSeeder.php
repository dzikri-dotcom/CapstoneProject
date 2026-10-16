<?php

namespace Tests\Support;

use App\Models\HourlyRate;
use Illuminate\Database\Seeder;

/**
 * Tarif untuk tes perhitungan gaji: slot jam bulat 08.00–17.00 dengan dua versi
 * (1 Jan 2026 semua Rp10.000; 1 Agu 2026 Rp11.000, jam 16–17 Rp13.500).
 * Dipisah dari HourlyRateSeeder (yang mengikuti jam operasional aplikasi) agar
 * angka harapan di tes penggajian tetap mudah dihitung.
 */
class LegacyHourlyRateSeeder extends Seeder
{
    public function run(): void
    {
        $versions = [
            '2026-01-01' => fn (int $hour) => 10000,
            '2026-08-01' => fn (int $hour) => $hour >= 16 ? 13500 : 11000,
        ];

        foreach ($versions as $effectiveDate => $rateForHour) {
            for ($hour = 8; $hour < 17; $hour++) {
                HourlyRate::query()->create([
                    'effective_date' => $effectiveDate,
                    'start_time' => sprintf('%02d:00:00', $hour),
                    'end_time' => sprintf('%02d:00:00', $hour + 1),
                    'rate' => $rateForHour($hour),
                ]);
            }
        }
    }
}
