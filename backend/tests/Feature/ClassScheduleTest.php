<?php

namespace Tests\Feature;

use App\Models\ClassSchedule;
use App\Models\StaffProfile;
use App\Models\User;
use Database\Seeders\OperationalHourSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ClassScheduleTest extends TestCase
{
    use RefreshDatabase;

    private StaffProfile $staff;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(OperationalHourSeeder::class); // Senin–Jumat 08:00–17:00
        $this->staff = StaffProfile::factory()->create();
    }

    public function test_staff_bisa_menambah_dan_melihat_jadwal_kuliahnya(): void
    {
        Sanctum::actingAs($this->staff->user);

        $this->postJson('/api/staff/class-schedules', [
            'day_of_week' => 1,
            'start_time' => '08:00',
            'end_time' => '10:30',
            'course_name' => 'Struktur Data',
        ])->assertCreated()->assertJsonPath('data.day_label', 'Senin')->assertJsonPath('data.end_time', '10:30');

        $this->getJson('/api/staff/class-schedules')->assertOk()->assertJsonCount(1, 'data');
    }

    public function test_jam_selesai_harus_setelah_jam_mulai(): void
    {
        Sanctum::actingAs($this->staff->user);

        $this->postJson('/api/staff/class-schedules', ['day_of_week' => 1, 'start_time' => '10:00', 'end_time' => '09:00'])
            ->assertStatus(422)
            ->assertJsonPath('errors.end_time.0', 'Jam selesai harus setelah jam mulai.');
    }

    public function test_jadwal_kuliah_yang_bentrok_dengan_kuliah_lain_ditolak(): void
    {
        ClassSchedule::factory()->for($this->staff)->create([
            'day_of_week' => 1, 'start_time' => '08:00', 'end_time' => '10:30', 'course_name' => 'Kalkulus',
        ]);
        Sanctum::actingAs($this->staff->user);

        $this->postJson('/api/staff/class-schedules', ['day_of_week' => 1, 'start_time' => '10:00', 'end_time' => '12:00'])
            ->assertStatus(422)
            ->assertJsonPath('errors.start_time.0', 'Bentrok dengan jadwal kuliah lain: Senin 08:00–10:30 (Kalkulus).');

        // Bersebelahan (mulai tepat saat kuliah lain selesai) diperbolehkan.
        $this->postJson('/api/staff/class-schedules', ['day_of_week' => 1, 'start_time' => '10:30', 'end_time' => '12:00'])
            ->assertCreated();
    }

    public function test_staff_tidak_bisa_mengubah_jadwal_kuliah_staff_lain(): void
    {
        $other = ClassSchedule::factory()->create();
        Sanctum::actingAs($this->staff->user);

        $payload = ['day_of_week' => 2, 'start_time' => '08:00', 'end_time' => '09:00'];
        $this->putJson("/api/staff/class-schedules/{$other->id}", $payload)
            ->assertForbidden()
            ->assertJsonPath('message', 'Anda tidak memiliki akses ke fitur ini.');
        $this->deleteJson("/api/staff/class-schedules/{$other->id}")->assertForbidden();

        $this->assertModelExists($other);
    }

    public function test_jam_kosong_dihitung_dari_jam_operasional_dikurangi_kuliah(): void
    {
        ClassSchedule::factory()->for($this->staff)->create(['day_of_week' => 1, 'start_time' => '08:00', 'end_time' => '10:30']);
        ClassSchedule::factory()->for($this->staff)->create(['day_of_week' => 1, 'start_time' => '13:00', 'end_time' => '15:30']);
        ClassSchedule::factory()->for($this->staff)->create(['day_of_week' => 6, 'start_time' => '08:00', 'end_time' => '10:00']); // Sabtu nonaktif
        Sanctum::actingAs($this->staff->user);

        $response = $this->getJson('/api/staff/availability')->assertOk();

        $this->assertCount(5, $response->json('data.days')); // hanya Senin–Jumat
        $response->assertJsonPath('data.days.0.day_label', 'Senin')
            ->assertJsonPath('data.days.0.free', [['start' => '10:30', 'end' => '13:00'], ['start' => '15:30', 'end' => '17:00']])
            ->assertJsonPath('data.days.0.free_minutes', 240)
            ->assertJsonPath('data.days.1.free_minutes', 540)
            ->assertJsonPath('data.total_operational_minutes', 5 * 540)
            ->assertJsonPath('data.total_free_minutes', 5 * 540 - 300);
    }

    public function test_admin_bisa_mengelola_jadwal_kuliah_staff(): void
    {
        Sanctum::actingAs(User::factory()->admin()->create());

        $id = $this->postJson("/api/admin/staff/{$this->staff->id}/class-schedules", [
            'day_of_week' => 3, 'start_time' => '07:30', 'end_time' => '10:00', 'course_name' => 'Bahasa Inggris',
        ])->assertCreated()->json('data.id');

        $this->putJson("/api/admin/class-schedules/{$id}", ['day_of_week' => 3, 'start_time' => '08:00', 'end_time' => '10:00'])
            ->assertOk()
            ->assertJsonPath('data.start_time', '08:00')
            ->assertJsonPath('data.course_name', null);

        $this->getJson("/api/admin/staff/{$this->staff->id}/availability")
            ->assertOk()
            ->assertJsonPath('data.days.2.free_minutes', 420);

        $this->deleteJson("/api/admin/class-schedules/{$id}")->assertOk();
        $this->assertSame(0, $this->staff->classSchedules()->count());
    }
}
