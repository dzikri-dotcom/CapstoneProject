<?php

namespace Tests\Feature\Admin;

use App\Enums\PayrollStatus;
use App\Models\HourlyRate;
use App\Models\OperationalHour;
use App\Models\PayrollPeriod;
use App\Models\User;
use Database\Seeders\OperationalHourSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\Support\LegacyHourlyRateSeeder;
use Tests\TestCase;

class HourlyRateTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo('2026-09-24 10:00:00');
        // Versi 1 Jan 2026 (semua Rp10.000) & 1 Agu 2026 (Rp11.000, mulai jam 16 Rp13.500)
        $this->seed([OperationalHourSeeder::class, LegacyHourlyRateSeeder::class]);
        // Kelas ini menguji pengelolaan tarif dengan 9 slot jam (08–17), jadi jam operasionalnya dipasang eksplisit.
        OperationalHour::query()->update(['open_time' => '08:00', 'close_time' => '17:00']);
        Sanctum::actingAs(User::factory()->admin()->create());
    }

    /** Tarif untuk 9 slot jam 08–17. */
    private function rates(int $amount): array
    {
        return collect(range(8, 16))->map(fn ($h) => [
            'start_time' => sprintf('%02d:00', $h),
            'end_time' => sprintf('%02d:00', $h + 1),
            'rate' => $amount,
        ])->all();
    }

    public function test_slot_tarif_mengikuti_jam_buka_operasional_dan_versi_lama_tetap_terbaca(): void
    {
        // Jam operasional 06:30–19:30 → slot 06:30–07:30 … 18:30–19:30 (13 slot).
        OperationalHour::query()->update(['open_time' => '06:30', 'close_time' => '19:30']);

        $slots = $this->getJson('/api/admin/hourly-rates')->assertOk()->json('data.slots');

        $this->assertCount(13, $slots);
        $this->assertSame(['start_time' => '06:30', 'end_time' => '07:30'], array_intersect_key($slots[0], ['start_time' => 1, 'end_time' => 1]));
        $this->assertSame('19:30', end($slots)['end_time']);
        // Versi 1 Agu (slot jam bulat 08–17): slot 08:30 memakai tarif jam 08–09; di luar 08–17 belum bertarif.
        $this->assertSame(11000, $slots[2]['rate']);
        $this->assertSame(13500, $slots[10]['rate']); // 16:30–17:30 mulai di dalam slot 16–17
        $this->assertNull($slots[0]['rate']);
    }

    public function test_slot_terakhir_dipendekkan_mengikuti_jam_tutup(): void
    {
        OperationalHour::query()->update(['open_time' => '06:30', 'close_time' => '19:00']);

        $slots = $this->getJson('/api/admin/hourly-rates')->assertOk()->json('data.slots');

        $this->assertSame(['18:30', '19:00'], [end($slots)['start_time'], end($slots)['end_time']]);
    }

    public function test_tarif_berlaku_hari_ini_dipetakan_ke_slot_jam_operasional(): void
    {
        $this->getJson('/api/admin/hourly-rates')
            ->assertOk()
            ->assertJsonPath('data.effective_date', '2026-08-01')
            ->assertJsonCount(9, 'data.slots')
            ->assertJsonPath('data.slots.0', ['start_time' => '08:00', 'end_time' => '09:00', 'rate' => 11000])
            ->assertJsonPath('data.slots.8.rate', 13500)
            ->assertJsonPath('data.missing_count', 0);
    }

    public function test_riwayat_menandai_versi_berlaku_dan_versi_lama(): void
    {
        $this->postJson('/api/admin/hourly-rates', ['effective_date' => '2026-10-01', 'rates' => $this->rates(12000)])
            ->assertCreated();

        $this->getJson('/api/admin/hourly-rates/history')
            ->assertOk()
            ->assertJsonCount(3, 'data')
            ->assertJsonPath('data.0.effective_date', '2026-10-01')
            ->assertJsonPath('data.0.status', 'upcoming')
            ->assertJsonPath('data.1.status', 'current')
            ->assertJsonPath('data.2.status', 'past')
            ->assertJsonPath('data.1.min_rate', 11000)
            ->assertJsonPath('data.1.max_rate', 13500);
    }

    public function test_menyimpan_tanggal_yang_sama_mengganti_versi_tersebut(): void
    {
        $this->postJson('/api/admin/hourly-rates', ['effective_date' => '2026-08-01', 'rates' => $this->rates(15000)])
            ->assertCreated()
            ->assertJsonPath('data.slots.8.rate', 15000);

        $this->assertSame(18, HourlyRate::count());
    }

    public function test_periode_lama_tetap_memakai_tarif_lama(): void
    {
        $this->postJson('/api/admin/hourly-rates', ['effective_date' => '2026-10-01', 'rates' => $this->rates(12000)]);

        $this->assertSame(10000, HourlyRate::effectiveOn('2026-07-31')->first()->rate);
        $this->assertSame(11000, HourlyRate::effectiveOn('2026-09-30')->first()->rate);
        $this->assertSame(12000, HourlyRate::effectiveOn('2026-10-01')->first()->rate);
    }

    public function test_tarif_tidak_boleh_berlaku_mundur_ke_periode_gaji_final(): void
    {
        PayrollPeriod::create([
            'start_date' => '2026-08-01', 'end_date' => '2026-08-31', 'status' => PayrollStatus::Final,
        ]);

        $this->postJson('/api/admin/hourly-rates', ['effective_date' => '2026-08-15', 'rates' => $this->rates(12000)])
            ->assertStatus(422)
            ->assertJsonPath('errors.effective_date.0', 'Tanggal berlaku harus setelah 31 Agustus 2026 karena periode gaji sampai tanggal tersebut sudah final.');

        $this->deleteJson('/api/admin/hourly-rates/2026-08-01')->assertStatus(422);
    }

    public function test_tarif_setiap_jam_wajib_diisi(): void
    {
        $rates = $this->rates(12000);
        $rates[3]['rate'] = null;

        $errors = $this->postJson('/api/admin/hourly-rates', ['effective_date' => '2026-10-01', 'rates' => $rates])
            ->assertStatus(422)
            ->json('errors');

        $this->assertSame('Tarif untuk setiap slot jam wajib diisi.', $errors['rates.3.rate'][0]);
    }

    public function test_versi_tarif_bisa_dihapus(): void
    {
        $this->postJson('/api/admin/hourly-rates', ['effective_date' => '2026-10-01', 'rates' => $this->rates(12000)]);

        $this->deleteJson('/api/admin/hourly-rates/2026-10-01')->assertOk();
        $this->deleteJson('/api/admin/hourly-rates/2026-10-01')->assertNotFound();

        $this->assertSame(18, HourlyRate::count());
    }
}
