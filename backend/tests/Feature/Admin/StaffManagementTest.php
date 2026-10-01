<?php

namespace Tests\Feature\Admin;

use App\Enums\UserRole;
use App\Models\SchedulePeriod;
use App\Models\StaffProfile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class StaffManagementTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Sanctum::actingAs(User::factory()->admin()->create());
    }

    private function validPayload(array $overrides = []): array
    {
        return array_merge([
            'name' => 'Rina Maharani',
            'email' => 'rina@logistik.test',
            'nim' => '1203230099',
            'phone' => '0812-3456-7890',
            'study_program' => 'S1 Informatika',
            'password' => 'rahasia123',
        ], $overrides);
    }

    public function test_admin_mengatur_batas_jam_mingguan_khusus_staff(): void
    {
        $staff = StaffProfile::factory()->create();
        $payload = [
            'name' => $staff->user->name,
            'email' => $staff->user->email,
            'nim' => $staff->nim,
            'study_program' => $staff->study_program,
            'is_active' => true,
        ];

        // Kosong = mengikuti batas umum di Pengaturan (bawaan 40 jam).
        $this->getJson("/api/admin/staff/{$staff->id}")
            ->assertOk()
            ->assertJsonPath('data.max_weekly_hours', null)
            ->assertJsonPath('data.effective_max_weekly_hours', 40);

        $this->putJson("/api/admin/staff/{$staff->id}", [...$payload, 'max_weekly_hours' => 20])
            ->assertOk()
            ->assertJsonPath('data.max_weekly_hours', 20)
            ->assertJsonPath('data.effective_max_weekly_hours', 20);

        $this->putJson("/api/admin/staff/{$staff->id}", [...$payload, 'max_weekly_hours' => 0])
            ->assertStatus(422)
            ->assertJsonPath('errors.max_weekly_hours.0', 'Batas jam per minggu harus bernilai antara 1 sampai 60.');

        // Dikosongkan lagi → kembali mengikuti batas umum.
        $this->putJson("/api/admin/staff/{$staff->id}", [...$payload, 'max_weekly_hours' => null])
            ->assertOk()
            ->assertJsonPath('data.max_weekly_hours', null);
    }

    public function test_daftar_staff_bisa_dicari_dan_difilter(): void
    {
        StaffProfile::factory()->create(['nim' => '1111111111'])->user->update(['name' => 'Budi Santoso']);
        StaffProfile::factory()->create(['nim' => '2222222222'])->user->update(['name' => 'Citra Lestari']);
        StaffProfile::factory()->inactive()->create();

        $this->getJson('/api/admin/staff')
            ->assertOk()
            ->assertJsonPath('summary.total', 3)
            ->assertJsonPath('summary.active', 2)
            ->assertJsonPath('meta.total', 3);

        $this->getJson('/api/admin/staff?search=budi')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.name', 'Budi Santoso');

        $this->getJson('/api/admin/staff?search=2222')
            ->assertJsonPath('data.0.name', 'Citra Lestari');

        $this->getJson('/api/admin/staff?status=inactive')
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.is_active', false);
    }

    public function test_tambah_staff_membuat_akun_login_dan_profil(): void
    {
        $this->postJson('/api/admin/staff', $this->validPayload())
            ->assertCreated()
            ->assertJsonPath('data.name', 'Rina Maharani')
            ->assertJsonPath('data.phone', '081234567890')
            ->assertJsonPath('data.is_active', true);

        $user = User::where('email', 'rina@logistik.test')->firstOrFail();
        $this->assertSame(UserRole::Staff, $user->role);
        $this->assertSame('1203230099', $user->staffProfile->nim);
        $this->assertTrue(Hash::check('rahasia123', $user->password));
    }

    public function test_validasi_tambah_staff_berbahasa_indonesia(): void
    {
        StaffProfile::factory()->create(['nim' => '1203230099']);

        $this->postJson('/api/admin/staff', $this->validPayload(['name' => '', 'phone' => '12345', 'password' => 'abc']))
            ->assertStatus(422)
            ->assertJsonPath('errors.name.0', 'Nama wajib diisi.')
            ->assertJsonPath('errors.nim.0', 'NIM sudah digunakan.')
            ->assertJsonPath('errors.phone.0', 'Format no. HP tidak valid. Contoh: 081234567890.')
            ->assertJsonPath('errors.password.0', 'Password minimal 8 karakter.');
    }

    public function test_ubah_staff_dan_nonaktifkan_mengeluarkan_sesi_staff(): void
    {
        $staff = StaffProfile::factory()->create();
        $staff->user->createToken('web');

        $this->putJson("/api/admin/staff/{$staff->id}", $this->validPayload([
            'email' => $staff->user->email,
            'nim' => $staff->nim,
            'is_active' => false,
        ]))->assertOk()->assertJsonPath('data.name', 'Rina Maharani')->assertJsonPath('data.is_active', false);

        $this->assertSame(0, $staff->user->tokens()->count());
    }

    public function test_staff_yang_punya_riwayat_jadwal_tidak_bisa_dihapus(): void
    {
        $staff = StaffProfile::factory()->create();
        $slot = SchedulePeriod::create(['start_date' => '2026-09-28', 'end_date' => '2026-10-02'])
            ->shiftSlots()->create(['date' => '2026-09-28', 'start_time' => '08:00', 'end_time' => '10:00', 'required_staff' => 2]);
        $slot->assignments()->create(['staff_profile_id' => $staff->id]);

        $this->deleteJson("/api/admin/staff/{$staff->id}")
            ->assertStatus(422)
            ->assertJsonPath('errors.staff.0', fn (string $message) => str_contains($message, 'Nonaktifkan saja'));

        $this->assertModelExists($staff);
    }

    public function test_staff_tanpa_riwayat_bisa_dihapus(): void
    {
        $staff = StaffProfile::factory()->create();
        $user = $staff->user;

        $this->deleteJson("/api/admin/staff/{$staff->id}")->assertOk();

        $this->assertModelMissing($staff);
        $this->assertModelMissing($user);
    }

    public function test_reset_password_staff(): void
    {
        $staff = StaffProfile::factory()->create();
        $staff->user->createToken('web');

        $this->postJson("/api/admin/staff/{$staff->id}/reset-password", ['password' => 'barubaru1'])->assertOk();

        $this->assertTrue(Hash::check('barubaru1', $staff->user->fresh()->password));
        $this->assertSame(0, $staff->user->tokens()->count());
    }

    public function test_staff_yang_tidak_ada_mengembalikan_404(): void
    {
        $this->getJson('/api/admin/staff/999')
            ->assertNotFound()
            ->assertJsonPath('message', 'Data tidak ditemukan.');
    }
}
