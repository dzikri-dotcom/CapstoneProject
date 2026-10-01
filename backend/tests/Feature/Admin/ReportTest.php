<?php

namespace Tests\Feature\Admin;

use App\Enums\AttendanceStatus;
use App\Enums\SchedulePeriodStatus;
use App\Models\Attendance;
use App\Models\SchedulePeriod;
use App\Models\StaffProfile;
use App\Models\User;
use Carbon\Carbon;
use Database\Seeders\HourlyRateSeeder;
use Database\Seeders\OperationalHourSeeder;
use Database\Seeders\SettingSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ReportTest extends TestCase
{
    use RefreshDatabase;

    private StaffProfile $andi;

    private StaffProfile $budi;

    private StaffProfile $citra;

    private SchedulePeriod $period;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo('2026-09-29 11:00:00'); // Selasa
        $this->seed([SettingSeeder::class, OperationalHourSeeder::class, HourlyRateSeeder::class]);

        [$this->andi, $this->budi, $this->citra] = collect(['Andi', 'Budi', 'Citra'])
            ->map(function ($name) {
                $staff = StaffProfile::factory()->create();
                $staff->user->update(['name' => $name]);

                return $staff;
            })->all();

        $this->period = SchedulePeriod::create([
            'start_date' => '2026-09-28', 'end_date' => '2026-10-04',
            'status' => SchedulePeriodStatus::Published, 'generated_at' => now(),
        ]);

        // Andi 4 jam, Budi 2 jam, Citra 0 jam.
        $this->shift($this->andi, '2026-09-28', '08:00', '10:00', '07:55', '10:00');
        $this->shift($this->andi, '2026-09-28', '10:00', '12:00', '10:20', '12:00'); // terlambat
        $this->shift($this->budi, '2026-09-28', '08:00', '10:00', null, null);       // tidak hadir
        $this->shift($this->andi, '2026-09-29', '10:00', '12:00', '09:58', null);   // sedang bertugas (hari ini)
    }

    private function shift(StaffProfile $staff, string $date, string $start, string $end, ?string $in, ?string $out): void
    {
        $slot = $this->period->shiftSlots()->firstOrCreate(['date' => $date, 'start_time' => $start], ['end_time' => $end, 'required_staff' => 2]);
        $assignment = $slot->assignments()->create(['staff_profile_id' => $staff->id]);
        $late = $in && $in > Carbon::parse($start)->addMinutes(10)->format('H:i');

        Attendance::create([
            'shift_assignment_id' => $assignment->id,
            'staff_profile_id' => $staff->id,
            'check_in_at' => $in ? Carbon::parse("{$date} {$in}") : null,
            'check_out_at' => $out ? Carbon::parse("{$date} {$out}") : null,
            'status' => ! $in ? AttendanceStatus::Absent : ($late ? AttendanceStatus::Late : AttendanceStatus::OnTime),
        ]);
    }

    public function test_laporan_keadilan_distribusi(): void
    {
        Sanctum::actingAs(User::factory()->admin()->create());

        $response = $this->getJson('/api/admin/reports/fairness?start_date=2026-09-28&end_date=2026-10-04')->assertOk();

        // Jam per staff: Andi 6 jam (3 shift), Budi 2 jam, Citra 0 jam → μ = 160 mnt
        $staff = collect($response->json('data.staff'))->keyBy('name');
        $this->assertSame(360, $staff['Andi']['minutes']);
        $this->assertSame(3, $staff['Andi']['shifts']);
        $this->assertSame(0, $staff['Citra']['minutes']); // staff aktif tanpa shift tetap dihitung

        $response->assertJsonPath('data.stats.count', 3)
            ->assertJsonPath('data.stats.mean', 160)
            ->assertJsonPath('data.stats.range', 360)
            ->assertJsonPath('data.stats.sd', 149.67) // √((200² + 40² + 160²) / 3) = √22.400
            ->assertJsonPath('data.periods.0.id', $this->period->id);
    }

    public function test_rekap_kehadiran_dan_ekspor_excel(): void
    {
        Sanctum::actingAs(User::factory()->admin()->create());

        $response = $this->getJson('/api/admin/reports/attendance?start_date=2026-09-28&end_date=2026-09-30')->assertOk();
        $andi = collect($response->json('data.staff'))->firstWhere('name', 'Andi');

        $this->assertSame(3, $andi['shifts']);
        $this->assertSame(1, $andi['late']);
        $this->assertSame(1, $andi['missing_checkout']);
        $response->assertJsonPath('data.totals.absent', 1)->assertJsonPath('data.totals.shifts', 4);

        $export = $this->get('/api/admin/reports/attendance/export?start_date=2026-09-28&end_date=2026-09-30')->assertOk();
        $this->assertStringContainsString('rekap-kehadiran_2026-09-28_2026-09-30.xlsx', $export->headers->get('content-disposition'));
    }

    public function test_rentang_laporan_divalidasi(): void
    {
        Sanctum::actingAs(User::factory()->admin()->create());

        $this->getJson('/api/admin/reports/fairness?start_date=2026-10-01&end_date=2026-09-01')
            ->assertStatus(422)
            ->assertJsonPath('errors.end_date.0', 'Tanggal selesai tidak boleh sebelum tanggal mulai.');
    }

    public function test_dashboard_admin(): void
    {
        Sanctum::actingAs(User::factory()->admin()->create());

        $this->getJson('/api/admin/dashboard')
            ->assertOk()
            ->assertJsonPath('data.active_staff', 3)
            ->assertJsonPath('data.today_shift_count', 1)
            ->assertJsonPath('data.on_duty.0.name', 'Andi')
            ->assertJsonPath('data.month_attendance.total', 4)
            ->assertJsonPath('data.month_attendance.absent', 1);

        // Slot hari ini & kemarin hanya berisi 1 dari 2 staff → masuk daftar kurang staff (mulai hari ini).
        $this->assertSame(['2026-09-29'], collect($this->getJson('/api/admin/dashboard')->json('data.understaffed'))->pluck('date')->unique()->values()->all());
    }

    public function test_dashboard_staff_estimasi_gaji_bulan_ini(): void
    {
        Sanctum::actingAs($this->andi->user);

        // Tanpa potongan gaji (bawaan): 3 shift yang dihadiri dibayar penuh, 3 × 120 mnt × Rp11.000/jam,
        // walaupun satu shift terlambat dan shift hari ini belum check-out.
        $this->getJson('/api/staff/dashboard')
            ->assertOk()
            ->assertJsonPath('data.current.start_time', '10:00')
            ->assertJsonPath('data.month.shifts_done', 3)
            ->assertJsonPath('data.month.paid_minutes', 360)
            ->assertJsonPath('data.month.estimated_amount', 66000)
            ->assertJsonPath('data.month.late', 1);
    }
}
