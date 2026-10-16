<?php

namespace Tests\Feature;

use App\Enums\AttendanceStatus;
use App\Enums\SchedulePeriodStatus;
use App\Models\Attendance;
use App\Models\SchedulePeriod;
use App\Models\ShiftAssignment;
use App\Models\StaffProfile;
use App\Services\Attendance\QrTokenService;
use Database\Seeders\OperationalHourSeeder;
use Database\Seeders\SettingSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Presensi berbasis sesi lewat satu endpoint scan QR.
 * Staff bertugas di dua shift berurutan (sesuai SOP), Senin 5 Okt 2026:
 *   Shift 1 06:30–09:30 di Pos 1, lalu Shift 2 09:30–11:30 di Pos 2.
 * Pengaturan bawaan: check-in dibuka 15 menit sebelum shift, terlambat lewat 10 menit,
 * check-in ditutup 30 menit setelah mulai, check-out paling lambat 30 menit setelah selesai.
 */
class AttendanceSessionTest extends TestCase
{
    use RefreshDatabase;

    private StaffProfile $staff;

    private ShiftAssignment $first;

    private ShiftAssignment $second;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo('2026-10-05 06:00:00');
        $this->seed([SettingSeeder::class, OperationalHourSeeder::class]);

        $this->staff = StaffProfile::factory()->create();
        $period = SchedulePeriod::create(['start_date' => '2026-10-05', 'end_date' => '2026-10-11', 'status' => SchedulePeriodStatus::Published]);

        $this->first = $period->shiftSlots()->create([
            'date' => '2026-10-05', 'start_time' => '06:30', 'end_time' => '09:30', 'required_staff' => 2,
            'shift_name' => 'Shift 1', 'post_name' => 'Pos 1',
        ])->assignments()->create(['staff_profile_id' => $this->staff->id]);

        $this->second = $period->shiftSlots()->create([
            'date' => '2026-10-05', 'start_time' => '09:30', 'end_time' => '11:30', 'required_staff' => 2,
            'shift_name' => 'Shift 2', 'post_name' => 'Pos 2',
        ])->assignments()->create(['staff_profile_id' => $this->staff->id]);

        Sanctum::actingAs($this->staff->user);
    }

    private function scan(string $at)
    {
        $this->travelTo("2026-10-05 {$at}:00");
        $token = app(QrTokenService::class)->current()->token;

        return $this->postJson('/api/staff/attendance/scan', ['token' => $token]);
    }

    private function attendanceOf(ShiftAssignment $assignment): ?Attendance
    {
        return Attendance::where('shift_assignment_id', $assignment->id)->first();
    }

    public function test_scan_pertama_check_in_dan_scan_berikutnya_check_out(): void
    {
        $this->scan('06:20')
            ->assertOk()
            ->assertJsonPath('action', 'check_in')
            ->assertJsonPath('message', 'Check-in Shift 1 (06:30–09:30, Pos 1) berhasil pukul 06:20 — Tepat waktu.')
            ->assertJsonPath('data.next_action', 'check_out');

        $this->scan('08:00')
            ->assertOk()
            ->assertJsonPath('action', 'check_out')
            ->assertJsonPath('message', 'Check-out Shift 1 (06:30–09:30, Pos 1) berhasil pukul 08:00. Terima kasih!');

        $this->assertSame(['06:20', '08:00'], [$this->attendanceOf($this->first)->check_in_at->format('H:i'), $this->attendanceOf($this->first)->check_out_at->format('H:i')]);
    }

    public function test_scan_ganda_setelah_check_in_tidak_langsung_check_out(): void
    {
        $this->scan('06:25')->assertOk();

        $this->scan('06:28')
            ->assertStatus(422)
            ->assertJsonPath('errors.attendance.0', 'Anda sudah check-in pukul 06:25 untuk Shift 1 (06:30–09:30, Pos 1). Scan lagi saat selesai bertugas untuk check-out.');

        $this->assertNull($this->attendanceOf($this->first)->check_out_at);
    }

    public function test_scan_ganda_saat_shift_berikutnya_sudah_buka_tidak_memicu_pergantian_sesi(): void
    {
        // Shift 1 dikoreksi agar check-in pukul 09:18 (mis. izin datang terlambat); jendela Shift 2 buka 09:15.
        $this->first->attendance()->create([
            'staff_profile_id' => $this->staff->id,
            'check_in_at' => '2026-10-05 09:18:00',
            'status' => AttendanceStatus::Late,
        ]);

        $this->scan('09:20')
            ->assertStatus(422)
            ->assertJsonPath('errors.attendance.0', 'Anda sudah check-in pukul 09:18 untuk Shift 1 (06:30–09:30, Pos 1). Scan lagi saat selesai bertugas untuk check-out.');

        $this->assertNull($this->attendanceOf($this->first)->check_out_at);
        $this->assertNull($this->attendanceOf($this->second));
    }

    public function test_shift_berurutan_satu_scan_untuk_pergantian_sesi(): void
    {
        $this->scan('06:25')->assertOk();

        // Jendela check-in Shift 2 sudah buka (09:15) sementara sesi Shift 1 masih terbuka.
        $this->travelTo('2026-10-05 09:20:00');
        $this->getJson('/api/staff/attendance/current')->assertJsonPath('data.next_action', 'handover');

        $this->scan('09:25')
            ->assertOk()
            ->assertJsonPath('action', 'handover')
            ->assertJsonPath('message', 'Pergantian sesi: check-out Shift 1 (06:30–09:30, Pos 1) dan check-in Shift 2 (09:30–11:30, Pos 2) pukul 09:25 — Tepat waktu.')
            ->assertJsonPath('data.next_action', 'check_out');

        $first = $this->attendanceOf($this->first);
        $second = $this->attendanceOf($this->second);
        $this->assertSame('09:25', $first->check_out_at->format('H:i'));
        $this->assertSame('09:25', $second->check_in_at->format('H:i'));
        $this->assertSame(AttendanceStatus::OnTime, $second->status);

        // Hanya ada satu sesi terbuka: scan berikutnya menutup Shift 2.
        $this->scan('11:35')->assertOk()->assertJsonPath('action', 'check_out');
        $this->assertSame('11:35', $this->attendanceOf($this->second)->check_out_at->format('H:i'));
    }

    public function test_check_in_shift_berikutnya_ditolak_selama_sesi_lama_masih_terbuka(): void
    {
        $this->scan('06:25')->assertOk();
        $this->travelTo('2026-10-05 09:20:00');
        $token = app(QrTokenService::class)->current()->token;

        $this->postJson('/api/staff/attendance/check-in', ['token' => $token])
            ->assertStatus(422)
            ->assertJsonPath('errors.attendance.0', 'Anda masih bertugas di shift 06:30–09:30. Lakukan check-out terlebih dahulu.');

        $this->assertNull($this->attendanceOf($this->second));
    }

    public function test_lupa_check_out_shift_pertama_tidak_menghalangi_shift_berikutnya(): void
    {
        $this->scan('06:25')->assertOk();

        // Batas check-out Shift 1 (10:00) sudah lewat → sesi lama tidak lagi terbuka.
        $this->scan('10:05')
            ->assertStatus(422)
            ->assertJsonPath('errors.attendance.0', 'Batas maksimal keterlambatan untuk shift 09:30–11:30 sudah lewat (check-in ditutup pukul 10:00). Hubungi koordinator jika perlu koreksi.');

        $this->getJson('/api/staff/attendance/current')
            ->assertJsonPath('data.today.0.state', 'missing_checkout')
            ->assertJsonPath('data.next_action', null);
    }

    public function test_check_in_terlambat_lewat_scan(): void
    {
        $this->scan('06:45')
            ->assertOk()
            ->assertJsonPath('message', 'Check-in Shift 1 (06:30–09:30, Pos 1) berhasil pukul 06:45 — Terlambat.');
    }

    public function test_scan_tanpa_shift_berjalan_ditolak_dengan_alasan(): void
    {
        $this->scan('06:00')
            ->assertStatus(422)
            ->assertJsonPath('errors.attendance.0', 'Check-in untuk shift 06:30–09:30 baru dibuka pukul 06:15.');

        $this->getJson('/api/staff/attendance/current')->assertJsonPath('data.next_action', null);
    }

    public function test_scan_dengan_qr_kedaluwarsa_ditolak(): void
    {
        $this->travelTo('2026-10-05 06:20:00');
        $token = app(QrTokenService::class)->current()->token;

        $this->travelTo('2026-10-05 06:21:00');
        $this->postJson('/api/staff/attendance/scan', ['token' => $token])
            ->assertStatus(422)
            ->assertJsonPath('errors.token.0', 'Kode QR sudah kedaluwarsa. Scan ulang QR yang sedang tampil di layar.');
    }
}
