<?php

namespace Tests\Feature\Admin;

use App\Enums\AttendanceStatus;
use App\Enums\PayrollStatus;
use App\Enums\SchedulePeriodStatus;
use App\Models\Attendance;
use App\Models\PayrollDetail;
use App\Models\PayrollPeriod;
use App\Models\SchedulePeriod;
use App\Models\Setting;
use App\Models\StaffProfile;
use App\Models\User;
use Carbon\Carbon;
use Database\Seeders\OperationalHourSeeder;
use Database\Seeders\SettingSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\Support\LegacyHourlyRateSeeder;
use Tests\TestCase;

/**
 * Tarif seeder: s.d. 31 Jul 2026 semua jam Rp10.000; mulai 1 Agu 2026 Rp11.000 (16–17 Rp13.500).
 * Pembulatan bawaan: per 30 menit ke bawah.
 *
 * Tes di kelas ini menguji aturan "gaji mengikuti menit hadir", sehingga potongan gaji diaktifkan.
 * Aturan bawaan (tanpa potongan) diuji di PayrollNoDeductionTest.
 */
class PayrollTest extends TestCase
{
    use RefreshDatabase;

    private StaffProfile $andi;

    private StaffProfile $budi;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo('2026-10-05 09:00:00');
        $this->seed([SettingSeeder::class, OperationalHourSeeder::class, LegacyHourlyRateSeeder::class]);
        Setting::where('key', 'salary_deduction_enabled')->update(['value' => '1']);

        $this->admin = User::factory()->admin()->create();
        $this->andi = StaffProfile::factory()->create();
        $this->andi->user->update(['name' => 'Andi Pratama']);
        $this->budi = StaffProfile::factory()->create();
        $this->budi->user->update(['name' => 'Budi Santoso']);

        $period = SchedulePeriod::create(['start_date' => '2026-07-13', 'end_date' => '2026-09-30', 'status' => SchedulePeriodStatus::Published]);

        // September (tarif Agustus)
        $this->shift($period, $this->andi, '2026-09-28', '08:00', '10:00', '08:10', '09:50');   // 100 mnt → 90 mnt → Rp16.500
        $this->shift($period, $this->andi, '2026-09-28', '16:00', '17:00', '15:50', '17:20');   // dibatasi 60 mnt × 13.500
        $this->shift($period, $this->andi, '2026-09-29', '08:00', '10:00', null, null);         // tidak hadir
        $this->shift($period, $this->budi, '2026-09-29', '10:00', '12:00', '10:00', null);      // belum check-out
        // Juli (tarif lama)
        $this->shift($period, $this->andi, '2026-07-15', '08:00', '10:00', '08:00', '10:00');   // 2 jam × 10.000

        Sanctum::actingAs($this->admin);
    }

    private function shift(SchedulePeriod $period, StaffProfile $staff, string $date, string $start, string $end, ?string $in, ?string $out): void
    {
        $slot = $period->shiftSlots()->firstOrCreate(
            ['date' => $date, 'start_time' => $start],
            ['end_time' => $end, 'required_staff' => 2],
        );
        $assignment = $slot->assignments()->create(['staff_profile_id' => $staff->id]);

        Attendance::create([
            'shift_assignment_id' => $assignment->id,
            'staff_profile_id' => $staff->id,
            'check_in_at' => $in ? Carbon::parse("{$date} {$in}") : null,
            'check_out_at' => $out ? Carbon::parse("{$date} {$out}") : null,
            'status' => $in ? AttendanceStatus::OnTime : AttendanceStatus::Absent,
        ]);
    }

    private function createPeriod(string $start = '2026-09-01', string $end = '2026-09-30'): int
    {
        return $this->postJson('/api/admin/payroll-periods', ['start_date' => $start, 'end_date' => $end])
            ->assertCreated()
            ->json('data.id');
    }

    private function detailFor(int $periodId, StaffProfile $staff): PayrollDetail
    {
        return PayrollDetail::where('payroll_period_id', $periodId)->where('staff_profile_id', $staff->id)->firstOrFail();
    }

    public function test_hitung_gaji_per_staff_dengan_pembulatan_dan_tarif_per_slot(): void
    {
        $id = $this->createPeriod();

        $this->postJson("/api/admin/payroll-periods/{$id}/calculate")
            ->assertOk()
            ->assertJsonPath('message', 'Gaji dihitung untuk 2 staff dengan total Rp30.000.');

        $andi = $this->detailFor($id, $this->andi);
        $this->assertSame(2, $andi->total_shifts);          // hadir 2 dari 3 shift
        $this->assertSame(150, $andi->total_minutes);        // 90 + 60
        $this->assertSame('30000.00', $andi->total_amount);  // 16.500 + 13.500

        $budi = $this->detailFor($id, $this->budi);
        $this->assertSame('0.00', $budi->total_amount);      // belum check-out → tidak dibayar

        $this->getJson("/api/admin/payroll-periods/{$id}")
            ->assertOk()
            ->assertJsonPath('data.total_amount', 30000)
            ->assertJsonPath('data.details.0.staff.name', 'Andi Pratama')
            ->assertJsonPath('data.details.0.absent_count', 1)
            ->assertJsonPath('data.settings_snapshot.rounding_unit_minutes', 30);

        $items = $this->getJson("/api/admin/payroll-details/{$andi->id}")->assertOk()->json('data.items');
        $this->assertSame(['08:10', '09:00'], [$items[0]['segments'][0]['from'], $items[0]['segments'][0]['to']]);
        $this->assertSame(16500, $items[0]['amount']);
        $this->assertSame(13500, $items[1]['amount']);
        $this->assertSame('Tidak hadir', $items[2]['note']);
    }

    public function test_pembulatan_per_menit_mengikuti_pengaturan(): void
    {
        Setting::where('key', 'rounding_unit_minutes')->update(['value' => '1']);
        $id = $this->createPeriod();

        $this->postJson("/api/admin/payroll-periods/{$id}/calculate")->assertOk();

        // 100 mnt × 11.000/60 = 18.333,33 + 13.500
        $this->assertSame('31833.33', $this->detailFor($id, $this->andi)->total_amount);
    }

    public function test_periode_lama_memakai_tarif_yang_berlaku_saat_itu(): void
    {
        $id = $this->createPeriod('2026-07-01', '2026-07-31');

        $this->postJson("/api/admin/payroll-periods/{$id}/calculate")->assertOk();

        $this->assertSame('20000.00', $this->detailFor($id, $this->andi)->total_amount);
    }

    public function test_setelah_final_gaji_terkunci(): void
    {
        $id = $this->createPeriod();

        $this->postJson("/api/admin/payroll-periods/{$id}/finalize")->assertOk();

        $period = PayrollPeriod::findOrFail($id);
        $this->assertSame(PayrollStatus::Final, $period->status);
        $this->assertSame('30000.00', $period->total_amount); // finalisasi menghitung ulang terlebih dahulu
        $this->assertSame($this->admin->id, $period->finalized_by);

        $locked = 'Periode gaji ini sudah final sehingga tidak bisa diubah.';
        $this->postJson("/api/admin/payroll-periods/{$id}/calculate")->assertStatus(422)->assertJsonPath('errors.period.0', $locked);
        $this->deleteJson("/api/admin/payroll-periods/{$id}")->assertStatus(422);
        $this->postJson('/api/admin/hourly-rates', [
            'effective_date' => '2026-09-15',
            'rates' => [['start_time' => '08:00', 'end_time' => '09:00', 'rate' => 50000]],
        ])->assertStatus(422);
    }

    public function test_periode_gaji_tidak_boleh_bertumpuk(): void
    {
        $this->createPeriod();

        $this->postJson('/api/admin/payroll-periods', ['start_date' => '2026-09-15', 'end_date' => '2026-10-14'])
            ->assertStatus(422)
            ->assertJsonPath('errors.start_date.0', 'Tanggal bertumpuk dengan periode gaji 1 Sep 2026 – 30 Sep 2026 yang sudah ada.');
    }

    public function test_ekspor_excel_rekap_gaji(): void
    {
        $id = $this->createPeriod();
        $this->postJson("/api/admin/payroll-periods/{$id}/calculate");

        $response = $this->get("/api/admin/payroll-periods/{$id}/export")->assertOk();

        $this->assertStringContainsString('rekap-gaji_2026-09-01_2026-09-30.xlsx', $response->headers->get('content-disposition'));
        $this->assertStringContainsString('spreadsheetml', $response->headers->get('content-type'));
    }

    public function test_slip_gaji_pdf(): void
    {
        $id = $this->createPeriod();
        $this->postJson("/api/admin/payroll-periods/{$id}/calculate");
        $detail = $this->detailFor($id, $this->andi);

        $response = $this->get("/api/admin/payroll-details/{$detail->id}/slip")->assertOk();

        $this->assertSame('application/pdf', $response->headers->get('content-type'));
        $this->assertStringStartsWith('%PDF', $response->getContent());
    }

    public function test_staff_hanya_melihat_gaji_miliknya_yang_sudah_final(): void
    {
        $id = $this->createPeriod();
        $this->postJson("/api/admin/payroll-periods/{$id}/calculate");

        Sanctum::actingAs($this->andi->user);
        $this->getJson('/api/staff/payrolls')->assertOk()->assertJsonCount(0, 'data'); // masih draft

        Sanctum::actingAs($this->admin);
        $this->postJson("/api/admin/payroll-periods/{$id}/finalize")->assertOk();
        // Finalisasi menghitung ulang, sehingga baris rincian dibuat ulang.
        $andiDetail = $this->detailFor($id, $this->andi);
        $budiDetail = $this->detailFor($id, $this->budi);

        Sanctum::actingAs($this->andi->user);
        $this->getJson('/api/staff/payrolls')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.total_amount', 30000)
            ->assertJsonPath('data.0.period.start_date', '2026-09-01');
        $this->getJson("/api/staff/payrolls/{$andiDetail->id}")->assertOk()->assertJsonCount(3, 'data.items');
        $this->get("/api/staff/payrolls/{$andiDetail->id}/slip")->assertOk();
        $this->getJson("/api/staff/payrolls/{$budiDetail->id}")->assertNotFound();
    }
}
