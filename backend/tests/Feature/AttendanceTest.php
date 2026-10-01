<?php

namespace Tests\Feature;

use App\Enums\AttendanceMethod;
use App\Enums\AttendanceStatus;
use App\Enums\PayrollStatus;
use App\Enums\SchedulePeriodStatus;
use App\Models\Attendance;
use App\Models\PayrollPeriod;
use App\Models\SchedulePeriod;
use App\Models\ShiftAssignment;
use App\Models\StaffProfile;
use App\Models\User;
use App\Services\Attendance\QrTokenService;
use Database\Seeders\OperationalHourSeeder;
use Database\Seeders\SettingSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Pengaturan bawaan: check-in dibuka 15 menit sebelum shift, toleransi terlambat 10 menit,
 * maksimal keterlambatan 30 menit (lewat dari itu tidak bisa check-in),
 * check-out paling lambat 30 menit setelah shift, token QR berganti tiap 30 detik (+10 detik toleransi).
 * Shift uji: Senin 28 Sep 2026, 08:00–10:00.
 */
class AttendanceTest extends TestCase
{
    use RefreshDatabase;

    private StaffProfile $staff;

    private SchedulePeriod $period;

    private ShiftAssignment $assignment;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo('2026-09-28 07:00:00');
        $this->seed([SettingSeeder::class, OperationalHourSeeder::class]);

        $this->staff = StaffProfile::factory()->create();
        $this->period = SchedulePeriod::create([
            'start_date' => '2026-09-28',
            'end_date' => '2026-10-04',
            'status' => SchedulePeriodStatus::Published,
        ]);
        $slot = $this->period->shiftSlots()->create([
            'date' => '2026-09-28', 'start_time' => '08:00', 'end_time' => '10:00', 'required_staff' => 2,
        ]);
        $this->assignment = $slot->assignments()->create(['staff_profile_id' => $this->staff->id]);
    }

    private function token(): string
    {
        return app(QrTokenService::class)->current()->token;
    }

    private function checkIn(?string $token = null)
    {
        Sanctum::actingAs($this->staff->user);

        return $this->postJson('/api/staff/attendance/check-in', ['token' => $token ?? $this->token()]);
    }

    private function checkOut()
    {
        Sanctum::actingAs($this->staff->user);

        return $this->postJson('/api/staff/attendance/check-out', ['token' => $this->token()]);
    }

    private function attendance(): ?Attendance
    {
        return Attendance::where('shift_assignment_id', $this->assignment->id)->first();
    }

    public function test_check_in_tepat_waktu(): void
    {
        $this->travelTo('2026-09-28 07:50:00');

        $this->checkIn()->assertOk()->assertJsonPath('message', 'Check-in berhasil pukul 07:50 — Tepat waktu.');

        $this->assertSame(AttendanceStatus::OnTime, $this->attendance()->status);
        $this->assertSame(AttendanceMethod::Qr, $this->attendance()->check_in_method);
    }

    public function test_batas_toleransi_keterlambatan(): void
    {
        $this->travelTo('2026-09-28 08:10:00'); // tepat di batas toleransi
        $this->checkIn()->assertOk();
        $this->assertSame(AttendanceStatus::OnTime, $this->attendance()->status);

        $this->attendance()->delete();
        $this->travelTo('2026-09-28 08:11:00'); // lewat toleransi
        $this->checkIn()->assertOk()->assertJsonPath('message', 'Check-in berhasil pukul 08:11 — Terlambat.');
        $this->assertSame(AttendanceStatus::Late, $this->attendance()->status);
    }

    public function test_check_in_masih_diterima_sampai_batas_maksimal_keterlambatan(): void
    {
        $this->travelTo('2026-09-28 08:29:00'); // 29 menit terlambat, batas 30 menit

        $this->checkIn()->assertOk()->assertJsonPath('message', 'Check-in berhasil pukul 08:29 — Terlambat.');
        $this->assertSame(AttendanceStatus::Late, $this->attendance()->status);
    }

    public function test_check_in_ditolak_setelah_maksimal_keterlambatan(): void
    {
        $this->travelTo('2026-09-28 08:30:00'); // tepat di batas → check-in sudah ditutup

        $this->checkIn()
            ->assertStatus(422)
            ->assertJsonPath(
                'errors.attendance.0',
                'Batas maksimal keterlambatan untuk shift 08:00–10:00 sudah lewat (check-in ditutup pukul 08:30). Hubungi koordinator jika perlu koreksi.',
            );
        $this->assertNull($this->attendance());

        Sanctum::actingAs($this->staff->user);
        $this->getJson('/api/staff/attendance/current')
            ->assertOk()
            ->assertJsonPath('data.check_in', null)
            ->assertJsonPath('data.today.0.state', 'missed')
            ->assertJsonPath('data.today.0.check_in_closes', '08:30');
    }

    public function test_maksimal_keterlambatan_bisa_diatur_admin(): void
    {
        Sanctum::actingAs(User::factory()->admin()->create());
        $settings = array_map(fn ($default) => $default['value'], \App\Models\Setting::DEFAULTS);
        $this->putJson('/api/admin/settings', [...$settings, 'max_late_minutes' => 60])->assertOk();

        $this->travelTo('2026-09-28 08:45:00');
        $this->checkIn()->assertOk();
        $this->assertSame(AttendanceStatus::Late, $this->attendance()->status);
    }

    public function test_maksimal_keterlambatan_tidak_melewati_jam_selesai_shift(): void
    {
        app(\App\Services\SettingService::class)->update(['max_late_minutes' => 480]);

        $this->travelTo('2026-09-28 10:00:00'); // shift 08:00–10:00 sudah selesai
        $this->checkIn()->assertStatus(422);
    }

    public function test_check_in_sebelum_jendela_dibuka_ditolak(): void
    {
        $this->travelTo('2026-09-28 07:40:00');

        $this->checkIn()
            ->assertStatus(422)
            ->assertJsonPath('errors.attendance.0', 'Check-in untuk shift 08:00–10:00 baru dibuka pukul 07:45.');
    }

    public function test_token_qr_kedaluwarsa_ditolak(): void
    {
        $this->travelTo('2026-09-28 07:50:00');
        $token = $this->token(); // berlaku s.d. 07:50:30 (+10 detik toleransi)

        $this->travelTo('2026-09-28 07:50:41');
        $this->checkIn($token)
            ->assertStatus(422)
            ->assertJsonPath('errors.token.0', 'Kode QR sudah kedaluwarsa. Scan ulang QR yang sedang tampil di layar.');
    }

    public function test_token_lama_masih_diterima_selama_toleransi_pergantian(): void
    {
        $this->travelTo('2026-09-28 07:50:00');
        $token = $this->token();

        $this->travelTo('2026-09-28 07:50:38');
        $this->checkIn($token)->assertOk();
    }

    public function test_token_qr_palsu_ditolak(): void
    {
        $this->travelTo('2026-09-28 07:50:00');

        $this->checkIn('token-palsu')
            ->assertStatus(422)
            ->assertJsonPath('errors.token.0', 'Kode QR tidak dikenali. Scan QR yang tampil di layar ruang logistik.');
    }

    public function test_shift_dari_jadwal_draft_tidak_bisa_dipresensi(): void
    {
        $this->period->update(['status' => SchedulePeriodStatus::Draft]);
        $this->travelTo('2026-09-28 07:50:00');

        $this->checkIn()
            ->assertStatus(422)
            ->assertJsonPath('errors.attendance.0', 'Tidak ada shift Anda yang sedang berlangsung saat ini.');
    }

    public function test_check_in_dua_kali_ditolak(): void
    {
        $this->travelTo('2026-09-28 07:50:00');
        $this->checkIn()->assertOk();

        $this->checkIn()
            ->assertStatus(422)
            ->assertJsonPath('errors.attendance.0', 'Anda sudah check-in untuk shift 08:00–10:00.');
    }

    public function test_check_out_dalam_batas_waktu(): void
    {
        $this->travelTo('2026-09-28 07:55:00');
        $this->checkIn()->assertOk();

        $this->travelTo('2026-09-28 10:05:00');
        $this->checkOut()->assertOk()->assertJsonPath('message', 'Check-out berhasil pukul 10:05. Terima kasih!');

        $this->assertSame('10:05', $this->attendance()->check_out_at->format('H:i'));
    }

    public function test_check_out_setelah_batas_waktu_ditolak(): void
    {
        $this->travelTo('2026-09-28 07:55:00');
        $this->checkIn()->assertOk();

        $this->travelTo('2026-09-28 10:31:00');
        $this->checkOut()->assertStatus(422);
        $this->assertNull($this->attendance()->check_out_at);
    }

    public function test_shift_berakhir_tanpa_check_in_ditandai_tidak_hadir(): void
    {
        $this->travelTo('2026-09-28 09:59:00');
        $this->artisan('attendance:mark-absent')->expectsOutput('0 penugasan shift ditandai tidak hadir.');
        $this->assertNull($this->attendance());

        $this->travelTo('2026-09-28 10:00:00');
        $this->artisan('attendance:mark-absent')->expectsOutput('1 penugasan shift ditandai tidak hadir.');

        $this->assertSame(AttendanceStatus::Absent, $this->attendance()->status);
        $this->assertNull($this->attendance()->check_in_at);
    }

    public function test_status_presensi_di_halaman_staff(): void
    {
        Sanctum::actingAs($this->staff->user);

        $this->travelTo('2026-09-28 07:30:00');
        $this->getJson('/api/staff/attendance/current')
            ->assertOk()
            ->assertJsonPath('data.check_in', null)
            ->assertJsonPath('data.next.start_time', '08:00')
            ->assertJsonPath('data.today.0.state', 'upcoming')
            ->assertJsonPath('data.today.0.check_in_opens', '07:45');

        $this->travelTo('2026-09-28 07:50:00');
        $this->getJson('/api/staff/attendance/current')
            ->assertJsonPath('data.check_in.assignment_id', $this->assignment->id)
            ->assertJsonPath('data.today.0.state', 'open');

        $this->checkIn()->assertOk()
            ->assertJsonPath('data.check_out.assignment_id', $this->assignment->id)
            ->assertJsonPath('data.today.0.state', 'in_progress');
    }

    public function test_admin_mengoreksi_presensi_dengan_catatan(): void
    {
        $this->travelTo('2026-09-28 10:00:00');
        $this->artisan('attendance:mark-absent');
        $admin = User::factory()->admin()->create();
        Sanctum::actingAs($admin);
        $url = "/api/admin/shift-assignments/{$this->assignment->id}/attendance";

        $this->putJson($url, ['status' => 'on_time', 'check_in_time' => '08:00', 'check_out_time' => '10:00'])
            ->assertStatus(422)
            ->assertJsonPath('errors.correction_note.0', 'Catatan alasan koreksi wajib diisi.');

        $this->putJson($url, ['status' => 'on_time', 'check_in_time' => '09:00', 'check_out_time' => '08:30', 'correction_note' => 'x'])
            ->assertStatus(422)
            ->assertJsonPath('errors.check_out_time.0', 'Jam check-out harus setelah jam check-in.');

        $this->putJson($url, [
            'status' => 'on_time',
            'check_in_time' => '07:58',
            'check_out_time' => '10:02',
            'correction_note' => 'HP staff mati, sudah dikonfirmasi hadir oleh rekan shift.',
        ])
            ->assertOk()
            ->assertJsonPath('data.state', 'done')
            ->assertJsonPath('data.attendance.worked_minutes', 124)
            ->assertJsonPath('data.attendance.corrected_by', $admin->name);

        $attendance = $this->attendance();
        $this->assertSame(AttendanceStatus::OnTime, $attendance->status);
        $this->assertTrue($attendance->is_corrected);
        $this->assertSame(AttendanceMethod::Manual, $attendance->check_in_method);
    }

    public function test_koreksi_ditolak_jika_periode_gaji_sudah_final(): void
    {
        PayrollPeriod::create(['start_date' => '2026-09-01', 'end_date' => '2026-09-30', 'status' => PayrollStatus::Final]);
        Sanctum::actingAs(User::factory()->admin()->create());

        $this->putJson("/api/admin/shift-assignments/{$this->assignment->id}/attendance", [
            'status' => 'absent',
            'correction_note' => 'Salah input',
        ])
            ->assertStatus(422)
            ->assertJsonPath('errors.attendance.0', 'Presensi ini termasuk periode gaji yang sudah final sehingga tidak bisa dikoreksi.');
    }

    public function test_monitoring_presensi_admin(): void
    {
        $this->travelTo('2026-09-28 07:50:00');
        $this->checkIn()->assertOk();
        Sanctum::actingAs(User::factory()->admin()->create());

        $this->getJson('/api/admin/attendances?date=2026-09-28')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.staff.name', $this->staff->user->name)
            ->assertJsonPath('data.0.state', 'in_progress')
            ->assertJsonPath('data.0.attendance.check_in_time', '07:50')
            ->assertJsonPath('summary.on_time', 1)
            ->assertJsonPath('summary.in_progress', 1);

        $this->getJson('/api/admin/attendances?date=2026-09-28&state=absent')->assertJsonCount(0, 'data');
    }

    public function test_token_qr_berganti_setelah_kedaluwarsa(): void
    {
        Sanctum::actingAs(User::factory()->admin()->create());

        $first = $this->postJson('/api/admin/qr-tokens/current')->assertOk()->json('data.token');
        $this->assertSame($first, $this->postJson('/api/admin/qr-tokens/current')->json('data.token'));

        $this->travel(31)->seconds();
        $this->assertNotSame($first, $this->postJson('/api/admin/qr-tokens/current')->json('data.token'));
    }

    public function test_staff_tidak_bisa_membuat_token_qr(): void
    {
        Sanctum::actingAs($this->staff->user);

        $this->postJson('/api/admin/qr-tokens/current')->assertForbidden();
    }

    public function test_riwayat_presensi_staff(): void
    {
        $this->travelTo('2026-09-28 07:55:00');
        $this->checkIn()->assertOk();
        $this->travelTo('2026-09-28 10:05:00');
        $this->checkOut()->assertOk();

        $this->getJson('/api/staff/attendances?month=2026-09')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.attendance.status_label', 'Tepat waktu')
            ->assertJsonPath('summary.on_time', 1)
            ->assertJsonPath('summary.worked_minutes', 130);
    }
}
