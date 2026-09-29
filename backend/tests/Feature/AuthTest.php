<?php

namespace Tests\Feature;

use App\Models\StaffProfile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AuthTest extends TestCase
{
    use RefreshDatabase;

    private function login(string $email, string $password = 'password'): string
    {
        return $this->postJson('/api/login', compact('email', 'password'))
            ->assertOk()
            ->json('token');
    }

    public function test_admin_bisa_login_dan_mendapat_token(): void
    {
        User::factory()->admin()->create(['email' => 'admin@logistik.test']);

        $this->postJson('/api/login', ['email' => 'ADMIN@logistik.test ', 'password' => 'password'])
            ->assertOk()
            ->assertJsonPath('user.role', 'admin')
            ->assertJsonStructure(['token', 'user' => ['id', 'name', 'email', 'role', 'role_label']]);
    }

    public function test_login_gagal_dengan_password_salah(): void
    {
        User::factory()->admin()->create(['email' => 'admin@logistik.test']);

        $this->postJson('/api/login', ['email' => 'admin@logistik.test', 'password' => 'salah'])
            ->assertStatus(422)
            ->assertJsonPath('errors.email.0', 'Email atau password salah.');
    }

    public function test_staff_nonaktif_tidak_bisa_login(): void
    {
        $staff = StaffProfile::factory()->inactive()->create();

        $this->postJson('/api/login', ['email' => $staff->user->email, 'password' => 'password'])
            ->assertStatus(422)
            ->assertJsonPath('errors.email.0', 'Akun Anda sedang nonaktif. Hubungi koordinator logistik.');
    }

    public function test_me_mengembalikan_data_profil_staff(): void
    {
        $staff = StaffProfile::factory()->create(['nim' => '1203220011']);
        $token = $this->login($staff->user->email);

        $this->withToken($token)->getJson('/api/me')
            ->assertOk()
            ->assertJsonPath('data.role', 'staff')
            ->assertJsonPath('data.staff_profile.nim', '1203220011');
    }

    public function test_tanpa_token_mendapat_401_berbahasa_indonesia(): void
    {
        $this->getJson('/api/me')
            ->assertUnauthorized()
            ->assertJsonPath('message', 'Sesi Anda telah berakhir. Silakan login kembali.');
    }

    public function test_logout_menghapus_token(): void
    {
        $staff = StaffProfile::factory()->create();
        $token = $this->login($staff->user->email);

        $this->withToken($token)->postJson('/api/logout')->assertOk();

        $this->assertSame(0, $staff->user->tokens()->count());
    }

    public function test_staff_tidak_bisa_mengakses_route_admin(): void
    {
        Sanctum::actingAs(StaffProfile::factory()->create()->user);

        $this->getJson('/api/admin/staff')
            ->assertForbidden()
            ->assertJsonPath('message', 'Anda tidak memiliki akses ke fitur ini.');
    }

    public function test_ganti_password_memerlukan_password_lama_yang_benar(): void
    {
        $staff = StaffProfile::factory()->create();
        $token = $this->login($staff->user->email);

        $this->withToken($token)->putJson('/api/me/password', [
            'current_password' => 'salah',
            'password' => 'passwordbaru',
            'password_confirmation' => 'passwordbaru',
        ])->assertStatus(422)->assertJsonPath('errors.current_password.0', 'Password saat ini salah.');

        $this->withToken($token)->putJson('/api/me/password', [
            'current_password' => 'password',
            'password' => 'passwordbaru',
            'password_confirmation' => 'passwordbaru',
        ])->assertOk();

        $this->postJson('/api/login', ['email' => $staff->user->email, 'password' => 'passwordbaru'])->assertOk();
    }

    public function test_login_dibatasi_setelah_terlalu_banyak_percobaan(): void
    {
        User::factory()->admin()->create(['email' => 'admin@logistik.test']);

        for ($i = 0; $i < 5; $i++) {
            $this->postJson('/api/login', ['email' => 'admin@logistik.test', 'password' => 'salah']);
        }

        $response = $this->postJson('/api/login', ['email' => 'admin@logistik.test', 'password' => 'salah'])
            ->assertStatus(429);

        $this->assertStringStartsWith('Terlalu banyak percobaan login', $response->json('message'));
    }
}
