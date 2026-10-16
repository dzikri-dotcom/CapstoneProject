<?php

namespace Tests\Feature;

use App\Enums\PayrollStatus;
use App\Enums\SchedulePeriodStatus;
use App\Models\Attendance;
use App\Models\PayrollPeriod;
use App\Models\SchedulePeriod;
use App\Models\ShiftAssignment;
use App\Models\ShiftSlot;
use App\Models\StaffProfile;
use Database\Seeders\DemoDataSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Data demo dijalankan pada Kamis 1 Okt 2026 pukul 11:00: Shift 1 (06:30–09:30) sudah selesai,
 * Shift 2 (09:30–11:30) sedang berlangsung, Shift 3 (11:30–14:30) belum mulai.
 */
class DemoDataSeederTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo('2026-10-01 11:00:00');
        $this->seed();
        $this->seed(DemoDataSeeder::class);
    }

    public function test_tidak_menambah_staff_dan_memakai_staff_unit(): void
    {
        $this->assertSame(15, StaffProfile::active()->count());
    }

    public function test_membuat_jadwal_minggu_lalu_minggu_ini_dan_minggu_depan(): void
    {
        $periods = SchedulePeriod::orderBy('start_date')->get();

        $this->assertSame(['2026-09-21', '2026-09-28', '2026-10-05'], $periods->map(fn ($p) => $p->start_date->toDateString())->all());
        $this->assertSame(
            [SchedulePeriodStatus::Published, SchedulePeriodStatus::Published, SchedulePeriodStatus::Draft],
            $periods->pluck('status')->all(),
        );

        // 5 hari × 5 shift × 3 pos per minggu, dan sebagian besar pos terisi penuh.
        $this->assertSame(225, ShiftSlot::count());
        $this->assertSame(['Pos 1', 'Pos 2', 'Pos 3'], ShiftSlot::distinct()->orderBy('post_name')->pluck('post_name')->all());
        $this->assertGreaterThan(400, ShiftAssignment::count()); // dari 450 tempat

        // Pointer berlanjut antar minggu.
        $this->assertSame($periods[0]->end_pointer_staff_id, $periods[1]->start_pointer_staff_id);
    }

    public function test_presensi_mengikuti_waktu_sekarang(): void
    {
        $attendanceOn = fn (string $date, string $start) => Attendance::whereHas(
            'shiftAssignment.shiftSlot',
            fn ($slot) => $slot->whereDate('date', $date)->where('start_time', $start),
        );

        // Shift yang sedang berlangsung: hanya check-in, belum check-out.
        $running = $attendanceOn('2026-10-01', '09:30:00')->get();
        $this->assertNotEmpty($running);
        $this->assertTrue($running->every(fn (Attendance $a) => $a->check_in_at !== null && $a->check_out_at === null));

        // Shift yang belum mulai dan jadwal draft tidak punya presensi.
        $this->assertSame(0, $attendanceOn('2026-10-01', '11:30:00')->count());
        $this->assertSame(0, Attendance::whereHas('shiftAssignment.shiftSlot', fn ($slot) => $slot->where('date', '>', '2026-10-01'))->count());

        // Minggu lalu lengkap: ada yang tepat waktu, terlambat, dan tidak hadir.
        $lastWeek = Attendance::whereHas('shiftAssignment.shiftSlot', fn ($slot) => $slot->whereBetween('date', ['2026-09-21', '2026-09-27']))->get();
        $this->assertGreaterThan(120, $lastWeek->count());
        $this->assertSame(['absent', 'late', 'on_time'], $lastWeek->map(fn ($a) => $a->status->value)->unique()->sort()->values()->all());
    }

    public function test_membuat_periode_gaji_bulan_berjalan(): void
    {
        $period = PayrollPeriod::firstOrFail();

        $this->assertSame('2026-10-01', $period->start_date->toDateString());
        $this->assertSame(PayrollStatus::Draft, $period->status);
        $this->assertGreaterThan(0, $period->total_amount);
        // Tanpa potongan gaji: yang hadir dibayar penuh — Shift 1 (3 jam), Shift 2 (2 jam), atau keduanya.
        $this->assertEmpty($period->details()->pluck('total_minutes')->diff([0, 120, 180, 300]));
    }

    public function test_aman_dijalankan_ulang(): void
    {
        $before = [SchedulePeriod::count(), StaffProfile::count(), Attendance::count(), PayrollPeriod::count()];

        $this->seed(DemoDataSeeder::class);

        $this->assertSame($before, [SchedulePeriod::count(), StaffProfile::count(), Attendance::count(), PayrollPeriod::count()]);
    }
}
