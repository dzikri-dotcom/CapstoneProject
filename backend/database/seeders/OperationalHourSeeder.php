<?php

namespace Database\Seeders;

use App\Enums\DayOfWeek;
use App\Models\OperationalHour;
use Illuminate\Database\Seeder;

class OperationalHourSeeder extends Seeder
{
    /**
     * Senin–Jumat 08.00–17.00 aktif; Sabtu & Minggu tersedia tetapi nonaktif.
     */
    public function run(): void
    {
        foreach (DayOfWeek::cases() as $day) {
            OperationalHour::query()->firstOrCreate(
                ['day_of_week' => $day->value],
                [
                    'is_active' => $day->value <= DayOfWeek::Friday->value,
                    'open_time' => '08:00:00',
                    'close_time' => '17:00:00',
                ],
            );
        }
    }
}
