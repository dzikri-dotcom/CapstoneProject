<?php

namespace Tests\Feature\Admin;

use App\Models\OperationalHour;
use App\Models\Setting;
use App\Models\StaffProfile;
use App\Models\User;
use Database\Seeders\OperationalHourSeeder;
use Database\Seeders\SettingSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class SettingsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([SettingSeeder::class, OperationalHourSeeder::class]);
        Sanctum::actingAs(User::factory()->admin()->create());
    }

    private function validSettings(array $overrides = []): array
    {
        return array_merge(array_map(fn ($d) => $d['value'], Setting::DEFAULTS), $overrides);
    }

    public function test_admin_melihat_pengaturan_bawaan(): void
    {
        $this->getJson('/api/admin/settings')
            ->assertOk()
            ->assertJsonPath('data.shift_duration_minutes', 120)
            ->assertJsonPath('data.staff_per_shift', 2)
            ->assertJsonPath('data.rounding_mode', 'down');
    }

    public function test_admin_bisa_mengubah_pengaturan(): void
    {
        $this->putJson('/api/admin/settings', $this->validSettings(['max_weekly_hours' => 10, 'rounding_unit_minutes' => 15]))
            ->assertOk()
            ->assertJsonPath('data.max_weekly_hours', 10)
            ->assertJsonPath('data.rounding_unit_minutes', 15);

        $this->assertSame('10', Setting::where('key', 'max_weekly_hours')->value('value'));
    }

    public function test_validasi_pengaturan(): void
    {
        $this->putJson('/api/admin/settings', $this->validSettings([
            'shift_duration_minutes' => 100,
            'rounding_mode' => 'up',
            'qr_refresh_seconds' => 10,
        ]))
            ->assertStatus(422)
            ->assertJsonPath('errors.shift_duration_minutes.0', 'Durasi shift harus kelipatan 30.')
            ->assertJsonPath('errors.rounding_mode.0', 'Cara pembulatan yang dipilih tidak valid.')
            ->assertJsonPath('errors.qr_refresh_seconds.0', 'Interval pergantian QR harus bernilai antara 30 sampai 60.');
    }

    public function test_admin_bisa_mengubah_jam_operasional(): void
    {
        $days = OperationalHour::orderBy('day_of_week')->get()->map(fn ($h) => [
            'day_of_week' => $h->day_of_week,
            'is_active' => $h->day_of_week <= 5,
            'open_time' => '08:00',
            'close_time' => $h->day_of_week === 5 ? '16:00' : '17:00',
        ])->all();
        $days[5]['is_active'] = true; // Sabtu diaktifkan
        $days[5]['close_time'] = '12:00';

        $this->putJson('/api/admin/operational-hours', ['days' => $days])
            ->assertOk()
            ->assertJsonPath('data.4.close_time', '16:00')
            ->assertJsonPath('data.5.is_active', true)
            ->assertJsonPath('data.5.day_label', 'Sabtu');
    }

    public function test_jam_tutup_harus_setelah_jam_buka_dan_minimal_satu_hari_aktif(): void
    {
        $days = collect(range(1, 7))->map(fn ($d) => [
            'day_of_week' => $d, 'is_active' => false, 'open_time' => '08:00', 'close_time' => '17:00',
        ])->all();
        $days[0]['close_time'] = '07:00';

        $errors = $this->putJson('/api/admin/operational-hours', ['days' => $days])
            ->assertStatus(422)
            ->json('errors');

        $this->assertSame('Jam tutup harus setelah jam buka.', $errors['days.0.close_time'][0]);
        $this->assertSame('Minimal satu hari operasional harus aktif.', $errors['days'][0]);
    }

    public function test_staff_tidak_bisa_mengubah_pengaturan(): void
    {
        Sanctum::actingAs(StaffProfile::factory()->create()->user);

        $this->putJson('/api/admin/settings', $this->validSettings())->assertForbidden();
    }
}
