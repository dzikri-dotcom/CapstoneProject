<?php

namespace App\Services;

use App\Enums\UserRole;
use App\Models\StaffProfile;
use App\Models\User;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Logika pengelolaan akun student staff. Satu staff = 1 baris users (akun login)
 * + 1 baris staff_profiles (data mahasiswa), sehingga selalu dibuat/diubah bersama.
 */
class StaffService
{
    public function create(array $data): StaffProfile
    {
        return DB::transaction(function () use ($data) {
            $user = User::create([
                'name' => $data['name'],
                'email' => $data['email'],
                'password' => $data['password'],
                'role' => UserRole::Staff,
                'email_verified_at' => now(),
            ]);

            return $user->staffProfile()->create([
                'nim' => $data['nim'],
                'phone' => $data['phone'] ?? null,
                'study_program' => $data['study_program'],
                'max_weekly_hours' => $data['max_weekly_hours'] ?? null,
                'is_active' => $data['is_active'] ?? true,
            ])->setRelation('user', $user);
        });
    }

    public function update(StaffProfile $staff, array $data): StaffProfile
    {
        DB::transaction(function () use ($staff, $data) {
            $staff->user->update(Arr::only($data, ['name', 'email']));
            $staff->update(Arr::only($data, ['nim', 'phone', 'study_program', 'max_weekly_hours', 'is_active']));

            // Staff yang dinonaktifkan langsung keluar dari semua perangkat.
            if ($staff->wasChanged('is_active') && ! $staff->is_active) {
                $staff->user->tokens()->delete();
            }
        });

        return $staff->refresh()->load('user');
    }

    public function resetPassword(StaffProfile $staff, string $password): void
    {
        $staff->user->update(['password' => $password]);
        $staff->user->tokens()->delete();
    }

    /**
     * Staff hanya bisa dihapus jika belum pernah dijadwalkan. Jika sudah punya
     * riwayat jadwal/presensi/gaji, cukup dinonaktifkan agar datanya tetap utuh.
     */
    public function delete(StaffProfile $staff): void
    {
        $hasHistory = $staff->shiftAssignments()->exists()
            || $staff->attendances()->exists()
            || $staff->payrollDetails()->exists();

        if ($hasHistory) {
            throw ValidationException::withMessages([
                'staff' => 'Staff ini sudah memiliki riwayat jadwal sehingga tidak bisa dihapus. '
                    .'Nonaktifkan saja agar tidak masuk jadwal berikutnya.',
            ]);
        }

        DB::transaction(function () use ($staff) {
            $staff->user->tokens()->delete();
            $staff->user->delete(); // profil & jadwal kuliah ikut terhapus (cascade)
        });
    }
}
