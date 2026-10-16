<?php

namespace Database\Seeders;

use App\Models\RoundRobinState;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

/**
 * Data untuk tes otomatis algoritma: sama seperti DatabaseSeeder, tetapi staff-nya memakai
 * ScenarioStaffSeeder (jadwal kuliah yang sengaja dibuat bentrok) agar hasil tes bisa dihitung pasti.
 */
class TestingDatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    public function run(): void
    {
        $this->call([
            SettingSeeder::class,
            OperationalHourSeeder::class,
            ShiftPostSeeder::class,
            HourlyRateSeeder::class,
            AdminSeeder::class,
            ScenarioStaffSeeder::class,
        ]);

        RoundRobinState::current();
    }
}
