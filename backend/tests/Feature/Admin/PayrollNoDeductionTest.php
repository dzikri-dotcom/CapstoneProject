<?php

namespace Tests\Feature\Admin;

use App\Enums\AttendanceStatus;
use App\Enums\SchedulePeriodStatus;
use App\Models\Attendance;
use App\Models\PayrollDetail;
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
 * Aturan bawaan: TIDAK ADA POTONGAN GAJI — staff yang hadir dibayar penuh sepanjang jam shift.
 * Tarif seeder mulai 1 Agu 2026: Rp11.000 per jam (08–16).
 */
class PayrollNoDeductionTest extends TestCase
{
    use RefreshDatabase;

    private StaffProfile $andi;

    private StaffProfile $budi;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo('2026-10-05 09:00:00');
        $this->seed([SettingSeeder::class, OperationalHourSeeder::class, LegacyHourlyRateSeeder::class]);

        $this->andi = StaffProfile::factory()->create();
        $this->budi = StaffProfile::factory()->create();
        $period = SchedulePeriod::create(['start_date' => '2026-09-28', 'end_date' => '2026-10-04', 'status' => SchedulePeriodStatus::Published]);

        // Shift Pagi 08:00–12:00 (4 jam × Rp11.000 = Rp44.000 bila dibayar penuh).
        $this->shift($period, $this->andi, '2026-09-28', '08:25', '11:30', AttendanceStatus::Late);   // terlambat & pulang cepat
        $this->shift($period, $this->andi, '2026-09-29', '07:55', '12:05', AttendanceStatus::OnTime); // normal
        $this->shift($period, $this->andi, '2026-09-30', null, null, AttendanceStatus::Absent);       // tidak hadir
        $this->shift($period, $this->budi, '2026-09-29', '08:00', null, AttendanceStatus::OnTime);    // lupa check-out

        Sanctum::actingAs(User::factory()->admin()->create());
    }

    private function shift(SchedulePeriod $period, StaffProfile $staff, string $date, ?string $in, ?string $out, AttendanceStatus $status): void
    {
        $slot = $period->shiftSlots()->firstOrCreate(
            ['date' => $date, 'start_time' => '08:00'],
            ['end_time' => '12:00', 'required_staff' => 2, 'shift_name' => 'Shift Pagi', 'post_name' => 'Pos 1'],
        );
        $assignment = $slot->assignments()->create(['staff_profile_id' => $staff->id]);

        Attendance::create([
            'shift_assignment_id' => $assignment->id,
            'staff_profile_id' => $staff->id,
            'check_in_at' => $in ? Carbon::parse("{$date} {$in}") : null,
            'check_out_at' => $out ? Carbon::parse("{$date} {$out}") : null,
            'status' => $status,
        ]);
    }

    private function calculatedPeriod(): int
    {
        $id = $this->postJson('/api/admin/payroll-periods', ['start_date' => '2026-09-01', 'end_date' => '2026-09-30'])->assertCreated()->json('data.id');
        $this->postJson("/api/admin/payroll-periods/{$id}/calculate")->assertOk();

        return $id;
    }

    private function detail(int $periodId, StaffProfile $staff): PayrollDetail
    {
        return PayrollDetail::where('payroll_period_id', $periodId)->where('staff_profile_id', $staff->id)->with('items')->firstOrFail();
    }

    public function test_bawaan_tidak_ada_potongan_gaji(): void
    {
        $this->getJson('/api/admin/settings')->assertOk()->assertJsonPath('data.salary_deduction_enabled', false);
    }

    public function test_terlambat_dan_pulang_cepat_tetap_dibayar_penuh(): void
    {
        $detail = $this->detail($this->calculatedPeriod(), $this->andi);
        $late = $detail->items->firstWhere(fn ($item) => $item->date->toDateString() === '2026-09-28');

        $this->assertSame(240, $late->paid_minutes);       // seluruh jam shift
        $this->assertSame(185, $late->actual_minutes);     // hadir sebenarnya 08:25–11:30 tetap tercatat
        $this->assertEquals(44000, $late->amount);

        // 2 shift hadir × Rp44.000; hari tidak hadir tidak dibayar.
        $this->assertSame(2, $detail->total_shifts);
        $this->assertSame(480, $detail->total_minutes);
        $this->assertEquals(88000, $detail->total_amount);
        $this->assertEquals(0, $detail->items->firstWhere(fn ($item) => $item->date->toDateString() === '2026-09-30')->amount);
    }

    public function test_lupa_check_out_tetap_dibayar_penuh(): void
    {
        $detail = $this->detail($this->calculatedPeriod(), $this->budi);

        $this->assertSame(240, $detail->total_minutes);
        $this->assertEquals(44000, $detail->total_amount);
    }

    public function test_potongan_bisa_diaktifkan_kembali_oleh_admin(): void
    {
        $settings = array_map(fn ($default) => $default['value'], Setting::DEFAULTS);
        $this->putJson('/api/admin/settings', [...$settings, 'salary_deduction_enabled' => true])
            ->assertOk()
            ->assertJsonPath('data.salary_deduction_enabled', true);

        $id = $this->calculatedPeriod();
        $andi = $this->detail($id, $this->andi);

        // 08:25–11:30 = 185 menit → dibulatkan ke bawah per 30 menit = 180 menit × Rp11.000 = Rp33.000.
        $late = $andi->items->firstWhere(fn ($item) => $item->date->toDateString() === '2026-09-28');
        $this->assertSame(180, $late->paid_minutes);
        $this->assertEquals(33000, $late->amount);
        $this->assertEquals(77000, $andi->total_amount);

        // Dengan potongan, lupa check-out tidak dibayar sampai dikoreksi admin.
        $this->assertEquals(0, $this->detail($id, $this->budi)->total_amount);

        $this->getJson("/api/admin/payroll-periods/{$id}")->assertJsonPath('data.settings_snapshot.salary_deduction_enabled', true);
    }
}
