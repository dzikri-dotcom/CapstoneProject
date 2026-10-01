<?php

namespace Database\Seeders;

use App\Enums\DayOfWeek;
use App\Enums\UserRole;
use App\Models\StaffProfile;
use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * 10 student staff dengan jadwal kuliah bervariasi dan sebagian saling bentrok.
 *
 * Skenario yang sengaja dibentuk untuk menguji algoritma (dipakai tes dengan slot shift 2 jam, 08.00–17.00):
 *  - Rabu 08–10  : 9 dari 10 staff kuliah (hanya Nabila kosong) → slot KURANG STAFF.
 *  - Kamis 12–14 : hanya Bagus & Nabila yang kosong → tepat terpenuhi, banyak staff dilewati.
 *  - Kamis 14–16 : hanya Andi & Nabila yang kosong → tepat terpenuhi.
 *  - Nabila (Bisnis Digital, angkatan 2023) paling longgar jadwalnya.
 * Semua akun staff memakai password: password
 */
class StaffSeeder extends Seeder
{
    public function run(): void
    {
        foreach ($this->staff() as $data) {
            $user = User::query()->firstOrCreate(
                ['email' => $data['email']],
                [
                    'name' => $data['name'],
                    'password' => 'password',
                    'role' => UserRole::Staff,
                    'email_verified_at' => now(),
                ],
            );

            $profile = StaffProfile::query()->updateOrCreate(
                ['user_id' => $user->id],
                [
                    'nim' => $data['nim'],
                    'phone' => $data['phone'],
                    'study_program' => $data['study_program'],
                    'is_active' => true,
                ],
            );

            // Jangan menimpa jadwal kuliah yang sudah diisi/diubah lewat aplikasi.
            if ($profile->classSchedules()->exists()) {
                continue;
            }

            foreach ($data['classes'] as [$day, $start, $end, $course]) {
                $profile->classSchedules()->create([
                    'day_of_week' => $day->value,
                    'start_time' => $start,
                    'end_time' => $end,
                    'course_name' => $course,
                ]);
            }
        }
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function staff(): array
    {
        $mon = DayOfWeek::Monday;
        $tue = DayOfWeek::Tuesday;
        $wed = DayOfWeek::Wednesday;
        $thu = DayOfWeek::Thursday;
        $fri = DayOfWeek::Friday;

        return [
            [
                'name' => 'Andi Pratama',
                'email' => 'andi.pratama@logistik.test',
                'nim' => '1203220011',
                'phone' => '081200000101',
                'study_program' => 'S1 Informatika',
                'classes' => [
                    [$mon, '08:00', '10:30', 'Struktur Data'],
                    [$mon, '13:00', '15:30', 'Basis Data'],
                    [$wed, '07:30', '10:00', 'Bahasa Inggris'],
                    [$thu, '10:30', '13:00', 'Pemrograman Web'],
                    [$fri, '13:00', '15:30', 'Sistem Operasi'],
                ],
            ],
            [
                'name' => 'Salsabila Putri',
                'email' => 'salsabila.putri@logistik.test',
                'nim' => '1204220023',
                'phone' => '081200000102',
                'study_program' => 'S1 Sistem Informasi',
                'classes' => [
                    [$mon, '10:30', '13:00', 'Analisis Proses Bisnis'],
                    [$tue, '08:00', '10:30', 'Manajemen Basis Data'],
                    [$wed, '08:00', '10:30', 'Pancasila'],
                    [$thu, '13:00', '15:30', 'Enterprise Resource Planning'],
                    [$thu, '15:30', '18:00', 'Statistika Bisnis'],
                ],
            ],
            [
                'name' => 'Muhammad Rizky Ramadhan',
                'email' => 'rizky.ramadhan@logistik.test',
                'nim' => '1206210035',
                'phone' => '081200000103',
                'study_program' => 'S1 Teknik Logistik',
                'classes' => [
                    [$tue, '10:30', '13:00', 'Manajemen Rantai Pasok'],
                    [$wed, '08:00', '10:30', 'Riset Operasi'],
                    [$wed, '13:00', '15:30', 'Manajemen Pergudangan'],
                    [$thu, '13:00', '15:30', 'Sistem Transportasi'],
                    [$fri, '08:00', '10:30', 'Simulasi Sistem'],
                ],
            ],
            [
                'name' => 'Dewi Anggraini',
                'email' => 'dewi.anggraini@logistik.test',
                'nim' => '1207230047',
                'phone' => '081200000104',
                'study_program' => 'S1 Teknik Industri',
                'classes' => [
                    [$mon, '08:00', '10:30', 'Kalkulus II'],
                    [$tue, '13:00', '15:30', 'Fisika Dasar'],
                    [$wed, '07:30', '10:00', 'Bahasa Inggris'],
                    [$wed, '10:30', '13:00', 'Statistika Industri'],
                    [$thu, '13:00', '15:30', 'Ergonomi'],
                    [$fri, '10:30', '13:00', 'Menggambar Teknik'],
                ],
            ],
            [
                'name' => 'Bagus Setiawan',
                'email' => 'bagus.setiawan@logistik.test',
                'nim' => '1201220052',
                'phone' => '081200000105',
                'study_program' => 'S1 Teknik Telekomunikasi',
                'classes' => [
                    [$mon, '15:30', '18:00', 'Sistem Komunikasi'],
                    [$tue, '08:00', '10:30', 'Elektromagnetika'],
                    [$wed, '08:00', '10:30', 'Pengolahan Sinyal'],
                    [$thu, '14:00', '16:30', 'Jaringan Telekomunikasi'],
                    [$fri, '08:00', '10:30', 'Antena dan Propagasi'],
                ],
            ],
            [
                'name' => 'Nabila Azzahra',
                'email' => 'nabila.azzahra@logistik.test',
                'nim' => '1208230064',
                'phone' => '081200000106',
                'study_program' => 'S1 Bisnis Digital',
                'classes' => [
                    [$mon, '10:30', '13:00', 'Pengantar Bisnis Digital'],
                    [$tue, '13:00', '15:30', 'Pemasaran Digital'],
                    [$wed, '13:00', '15:30', 'Akuntansi Dasar'],
                    [$fri, '13:00', '15:30', 'E-Commerce'],
                ],
            ],
            [
                'name' => 'Fajar Nugroho',
                'email' => 'fajar.nugroho@logistik.test',
                'nim' => '1202210078',
                'phone' => '081200000107',
                'study_program' => 'S1 Teknik Elektro',
                'classes' => [
                    [$mon, '13:00', '15:30', 'Sistem Kendali'],
                    [$wed, '08:00', '10:30', 'Elektronika Daya'],
                    [$thu, '08:00', '10:30', 'Mikroprosesor'],
                    [$thu, '13:00', '15:30', 'Praktikum Elektronika'],
                    [$fri, '15:30', '18:00', 'Metodologi Penelitian'],
                ],
            ],
            [
                'name' => 'Putri Ayu Lestari',
                'email' => 'putri.lestari@logistik.test',
                'nim' => '1205220081',
                'phone' => '081200000108',
                'study_program' => 'S1 Rekayasa Perangkat Lunak',
                'classes' => [
                    [$mon, '08:00', '10:30', 'Rekayasa Kebutuhan'],
                    [$tue, '10:30', '13:00', 'Pengujian Perangkat Lunak'],
                    [$wed, '07:30', '10:00', 'Bahasa Inggris'],
                    [$thu, '13:00', '15:30', 'Manajemen Proyek Perangkat Lunak'],
                    [$thu, '15:30', '18:00', 'Desain Antarmuka'],
                ],
            ],
            [
                'name' => 'Rendi Kurniawan',
                'email' => 'rendi.kurniawan@logistik.test',
                'nim' => '1209230093',
                'phone' => '081200000109',
                'study_program' => 'S1 Teknik Komputer',
                'classes' => [
                    [$tue, '08:00', '10:30', 'Organisasi Komputer'],
                    [$wed, '08:00', '10:30', 'Kalkulus II'],
                    [$wed, '10:30', '13:00', 'Logika Digital'],
                    [$thu, '13:00', '15:30', 'Pemrograman Mikrokontroler'],
                    [$fri, '10:30', '13:00', 'Jaringan Komputer'],
                ],
            ],
            [
                'name' => 'Aulia Rahmawati',
                'email' => 'aulia.rahmawati@logistik.test',
                'nim' => '1210220105',
                'phone' => '081200000110',
                'study_program' => 'S1 Sains Data',
                'classes' => [
                    [$mon, '10:30', '13:00', 'Machine Learning'],
                    [$wed, '08:00', '10:30', 'Pancasila'],
                    [$wed, '15:30', '18:00', 'Visualisasi Data'],
                    [$thu, '08:00', '10:30', 'Statistika Inferensial'],
                    [$thu, '13:00', '15:30', 'Big Data'],
                ],
            ],
        ];
    }
}
