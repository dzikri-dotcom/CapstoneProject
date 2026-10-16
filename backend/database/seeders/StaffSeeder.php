<?php

namespace Database\Seeders;

use App\Enums\UserRole;
use App\Models\StaffProfile;
use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * 15 student staff unit logistik (daftar nama dari lembar SOP).
 *
 * Email login: <nama>@logistik.test, password: password.
 * NIM, no. HP, program studi, dan jadwal kuliah di bawah adalah CONTOH — sesuaikan lewat menu
 * Student Staff (admin) atau menu Jadwal Kuliah (staff) dengan data sebenarnya.
 * Aman dijalankan ulang: data & jadwal kuliah yang sudah diubah lewat aplikasi tidak ditimpa.
 */
class StaffSeeder extends Seeder
{
    public function run(): void
    {
        foreach ($this->staff() as $index => [$name, $program, $classes]) {
            $user = User::query()->firstOrCreate(
                ['email' => strtolower($name).'@logistik.test'],
                [
                    'name' => $name,
                    'password' => 'password',
                    'role' => UserRole::Staff,
                    'email_verified_at' => now(),
                ],
            );

            $profile = StaffProfile::query()->firstOrCreate(
                ['user_id' => $user->id],
                [
                    'nim' => sprintf('12%02d23%04d', 1 + $index % 9, 101 + $index),
                    'phone' => sprintf('0812300001%02d', $index + 1),
                    'study_program' => $program,
                    'is_active' => true,
                ],
            );

            if ($profile->classSchedules()->exists()) {
                continue;
            }

            foreach ($classes as [$day, $start, $end, $course]) {
                $profile->classSchedules()->create([
                    'day_of_week' => $day,
                    'start_time' => $start,
                    'end_time' => $end,
                    'course_name' => $course,
                ]);
            }
        }
    }

    /**
     * [nama, program studi (contoh), jadwal kuliah (contoh)]. Hari: 1 = Senin … 5 = Jumat.
     *
     * @return list<array{0:string, 1:string, 2:list<array{0:int, 1:string, 2:string, 3:string}>}>
     */
    private function staff(): array
    {
        return [
            ['Bintang', 'S1 Teknik Logistik', [[1, '08:00', '10:30', 'Manajemen Rantai Pasok'], [3, '13:00', '15:30', 'Riset Operasi']]],
            ['Dziyan', 'S1 Informatika', [[2, '10:30', '13:00', 'Struktur Data'], [4, '08:00', '10:30', 'Basis Data']]],
            ['Taufiq', 'S1 Sistem Informasi', [[1, '13:00', '15:30', 'Analisis Proses Bisnis'], [5, '08:00', '10:30', 'Tata Kelola TI']]],
            ['Hanny', 'S1 Bisnis Digital', [[2, '08:00', '10:30', 'Pemasaran Digital'], [4, '13:00', '15:30', 'E-Commerce']]],
            ['Fitria', 'S1 Teknik Industri', [[3, '08:00', '10:30', 'Statistika Industri'], [5, '13:00', '15:30', 'Ergonomi']]],
            ['Raya', 'S1 Sains Data', [[1, '10:30', '13:00', 'Probabilitas'], [4, '10:30', '13:00', 'Pembelajaran Mesin']]],
            ['Khosyi', 'S1 Teknik Elektro', [[2, '13:00', '15:30', 'Rangkaian Listrik'], [3, '10:30', '13:00', 'Elektronika']]],
            ['Annisa', 'S1 Teknik Telekomunikasi', [[1, '08:00', '10:30', 'Sistem Komunikasi'], [5, '10:30', '13:00', 'Pengolahan Sinyal']]],
            ['Ruby', 'S1 Rekayasa Perangkat Lunak', [[2, '08:00', '10:30', 'Pengujian Perangkat Lunak'], [4, '15:30', '18:00', 'Pemrograman Web']]],
            ['Dian', 'S1 Teknik Komputer', [[3, '15:30', '18:00', 'Sistem Tertanam'], [5, '08:00', '10:30', 'Arsitektur Komputer']]],
            ['Salsa', 'S1 Sistem Informasi', [[1, '15:30', '18:00', 'Manajemen Proyek'], [4, '08:00', '10:30', 'Audit Sistem Informasi']]],
            ['Faizah', 'S1 Teknik Logistik', [[2, '15:30', '18:00', 'Manajemen Pergudangan'], [3, '08:00', '10:30', 'Sistem Transportasi']]],
            ['Baiti', 'S1 Bisnis Digital', [[1, '10:30', '13:00', 'Akuntansi Dasar'], [5, '13:00', '15:30', 'Kewirausahaan']]],
            ['Safira', 'S1 Teknologi Informasi', [[2, '10:30', '13:00', 'Jaringan Komputer'], [4, '13:00', '15:30', 'Keamanan Informasi']]],
            ['Dimas', 'S1 Teknik Industri', [[3, '10:30', '13:00', 'Penelitian Operasional'], [5, '15:30', '18:00', 'Simulasi Sistem']]],
        ];
    }
}
