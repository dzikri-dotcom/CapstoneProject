<?php

namespace Database\Seeders;

use App\Models\RoundRobinState;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     *
     * Semua seeder aman dijalankan ulang (tidak membuat data ganda).
     */
    public function run(): void
    {
        $this->call([
            SettingSeeder::class,
            OperationalHourSeeder::class,
            ShiftPostSeeder::class,
            HourlyRateSeeder::class,
            AdminSeeder::class,
            StaffSeeder::class,
        ]);

        // Pointer Round Robin awal masih kosong → periode pertama mulai dari staff pertama.
        RoundRobinState::current();
    }
}
