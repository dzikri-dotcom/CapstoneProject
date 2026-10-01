<?php

namespace Database\Seeders;

use App\Enums\DayOfWeek;
use App\Models\OperationalHour;
use Illuminate\Database\Seeder;

class OperationalHourSeeder extends Seeder
{
    /**
     * Senin–Jumat 06.30–19.30 aktif (mencakup Shift 1 s.d. Shift 5); Sabtu & Minggu tersedia tetapi nonaktif.
     */
    public function run(): void
    {
        foreach (DayOfWeek::cases() as $day) {
            OperationalHour::query()->firstOrCreate(
                ['day_of_week' => $day->value],
                [
                    'is_active' => $day->value <= DayOfWeek::Friday->value,
                    'open_time' => '06:30:00',
                    'close_time' => '19:30:00',
                ],
            );
        }
    }
}
