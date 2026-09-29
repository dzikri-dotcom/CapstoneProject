<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Tandai shift yang berakhir tanpa check-in sebagai "tidak hadir".
// Jalankan `php artisan schedule:work` di terminal terpisah agar berjalan otomatis
// (halaman presensi juga menjalankannya saat dibuka, sebagai cadangan).
Schedule::command('attendance:mark-absent')->everyFiveMinutes();
