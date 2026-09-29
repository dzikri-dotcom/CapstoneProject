<?php

namespace Database\Seeders;

use App\Models\HourlyRate;
use Illuminate\Database\Seeder;

class HourlyRateSeeder extends Seeder
{
    /**
     * Dua versi tarif per slot jam (08.00–17.00) untuk menunjukkan riwayat tarif:
     *  - berlaku 1 Jan 2026 : semua jam Rp10.000
     *  - berlaku 1 Agu 2026 : 08.00–16.00 Rp11.000, 16.00–17.00 Rp13.500 (jam sore lebih tinggi)
     * Penggajian periode sebelum Agustus 2026 otomatis memakai versi pertama.
     */
    public function run(): void
    {
        $versions = [
            '2026-01-01' => fn (int $hour) => 10000,
            '2026-08-01' => fn (int $hour) => $hour >= 16 ? 13500 : 11000,
        ];

        foreach ($versions as $effectiveDate => $rateForHour) {
            for ($hour = 8; $hour < 17; $hour++) {
                HourlyRate::query()->firstOrCreate(
                    [
                        'effective_date' => $effectiveDate,
                        'start_time' => sprintf('%02d:00:00', $hour),
                    ],
                    [
                        'end_time' => sprintf('%02d:00:00', $hour + 1),
                        'rate' => $rateForHour($hour),
                    ],
                );
            }
        }
    }
}
