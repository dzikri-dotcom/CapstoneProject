<?php

namespace Database\Seeders;

use App\Models\HourlyRate;
use App\Services\HourlyRateService;
use App\Support\Time;
use Illuminate\Database\Seeder;

class HourlyRateSeeder extends Seeder
{
    /**
     * Dua versi tarif per slot jam yang mengikuti jam operasional (bawaan 06.30–19.30,
     * sehingga slotnya 06.30–07.30, 07.30–08.30, …, 18.30–19.30) untuk menunjukkan riwayat tarif:
     *  - berlaku 1 Jan 2026 : semua jam Rp10.000
     *  - berlaku 1 Agu 2026 : Rp11.000, mulai 16.30 Rp13.500 (jam sore lebih tinggi)
     * Penggajian periode sebelum Agustus 2026 otomatis memakai versi pertama.
     * Jalankan setelah OperationalHourSeeder.
     */
    public function run(HourlyRateService $rates): void
    {
        $versions = [
            '2026-01-01' => fn (int $start) => 10000,
            '2026-08-01' => fn (int $start) => $start >= Time::toMinutes('16:30') ? 13500 : 11000,
        ];

        foreach ($versions as $effectiveDate => $rateFor) {
            foreach ($rates->hourSlots() as $slot) {
                HourlyRate::query()->firstOrCreate(
                    ['effective_date' => $effectiveDate, 'start_time' => $slot['start_time'].':00'],
                    ['end_time' => $slot['end_time'].':00', 'rate' => $rateFor(Time::toMinutes($slot['start_time']))],
                );
            }
        }
    }
}
