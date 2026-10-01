<?php

namespace Database\Seeders;

use App\Models\Post;
use App\Models\Shift;
use Illuminate\Database\Seeder;

/**
 * Shift & pos jaga sesuai SOP student staff unit:
 *
 *   Shift 1  06.30–09.30      Pos 1, Pos 2 — Gedung A
 *   Shift 2  09.30–11.30      Pos 3        — Gedung B
 *   Shift 3  11.30–14.30      (maksimal 2 orang per pos)
 *   Shift 4  14.30–16.30
 *   Shift 5  16.30–19.30
 *
 * Hanya diisi jika tabelnya masih kosong, sehingga perubahan admin di halaman Pengaturan
 * tidak tertimpa saat seeder dijalankan ulang.
 */
class ShiftPostSeeder extends Seeder
{
    public const SHIFTS = [
        'Shift 1' => ['06:30', '09:30'],
        'Shift 2' => ['09:30', '11:30'],
        'Shift 3' => ['11:30', '14:30'],
        'Shift 4' => ['14:30', '16:30'],
        'Shift 5' => ['16:30', '19:30'],
    ];

    public const POSTS = [
        'Pos 1' => 'Gedung A',
        'Pos 2' => 'Gedung A',
        'Pos 3' => 'Gedung B',
    ];

    public function run(): void
    {
        if (! Shift::query()->exists()) {
            foreach (self::SHIFTS as $name => [$start, $end]) {
                Shift::query()->create(['name' => $name, 'start_time' => $start, 'end_time' => $end]);
            }
        }

        if (! Post::query()->exists()) {
            foreach (self::POSTS as $name => $location) {
                Post::query()->create(['name' => $name, 'location' => $location, 'capacity' => Post::MAX_CAPACITY]);
            }
        }
    }
}
