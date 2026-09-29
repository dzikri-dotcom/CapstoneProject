<?php

namespace Database\Seeders;

use App\Models\Setting;
use Illuminate\Database\Seeder;

class SettingSeeder extends Seeder
{
    /**
     * Isi pengaturan bawaan. Memakai firstOrCreate agar nilai yang sudah
     * diubah admin tidak tertimpa jika seeder dijalankan ulang.
     */
    public function run(): void
    {
        foreach (Setting::DEFAULTS as $key => $default) {
            Setting::query()->firstOrCreate(
                ['key' => $key],
                ['value' => (string) $default['value'], 'type' => $default['type']],
            );
        }
    }
}
