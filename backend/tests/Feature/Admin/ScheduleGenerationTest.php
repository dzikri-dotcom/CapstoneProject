<?php

namespace Tests\Feature\Admin;

use App\Models\Post;
use App\Models\RoundRobinState;
use App\Models\SchedulePeriod;
use App\Models\Setting;
use App\Models\Shift;
use App\Models\ShiftAssignment;
use App\Models\ShiftSlot;
use App\Models\StaffProfile;
use App\Models\User;
use Database\Seeders\TestingDatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Generate & edit jadwal memakai data seeder (10 staff dengan jadwal kuliah yang sebagian bentrok).
 * Periode uji: Senin 28 Sep – Minggu 4 Okt 2026.
 *
 * Untuk menguji giliran Round Robin secara terisolasi, shift diatur menjadi lima shift 2 jam
 * (08–10, 10–12, 12–14, 14–16, 16–17) dengan satu pos berisi 2 orang. Jadwal dengan beberapa
 * pos jaga diuji di ShiftPostTest.
 */
class ScheduleGenerationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo('2026-09-24 10:00:00');
        $this->seed(TestingDatabaseSeeder::class);
        $this->useTwoHourShiftsWithOnePost();
        // Angka harapan di kelas ini dihitung dengan batas 12 jam per minggu.
        Setting::query()->updateOrCreate(['key' => 'max_weekly_hours'], ['value' => '12', 'type' => 'integer']);
        Sanctum::actingAs(User::where('email', 'admin@logistik.test')->firstOrFail());
    }

    private function useTwoHourShiftsWithOnePost(): void
    {
        Shift::query()->delete();
        Post::query()->delete();

        foreach ([['08:00', '10:00'], ['10:00', '12:00'], ['12:00', '14:00'], ['14:00', '16:00'], ['16:00', '17:00']] as $index => [$start, $end]) {
            Shift::create(['name' => 'Shift '.($index + 1), 'start_time' => $start, 'end_time' => $end]);
        }
        Post::create(['name' => 'Pos 1', 'capacity' => 2]);
    }

    private function createPeriod(string $start = '2026-09-28', string $end = '2026-10-04'): int
    {
        return $this->postJson('/api/admin/schedule-periods', ['start_date' => $start, 'end_date' => $end])
            ->assertCreated()
            ->json('data.id');
    }

    private function generatedPeriod(): int
    {
        $id = $this->createPeriod();
        $this->postJson("/api/admin/schedule-periods/{$id}/generate")->assertOk();

        return $id;
    }

    private function slot(int $periodId, string $date, string $start): ShiftSlot
    {
        return ShiftSlot::where('schedule_period_id', $periodId)->where('date', $date)->where('start_time', $start)->firstOrFail();
    }

    private function staffId(string $email): int
    {
        return User::where('email', $email)->firstOrFail()->staffProfile->id;
    }

    /** @return array<string, list<int>> "tanggal jam" → ID staff */
    private function snapshot(int $periodId): array
    {
        return ShiftSlot::where('schedule_period_id', $periodId)->with('assignments')->orderBy('date')->orderBy('start_time')->get()
            ->mapWithKeys(fn (ShiftSlot $slot) => [
                $slot->date->toDateString().' '.$slot->start_time => $slot->assignments->pluck('staff_profile_id')->sort()->values()->all(),
            ])->all();
    }

    public function test_generate_membentuk_slot_dan_menandai_slot_kurang_staff(): void
    {
        $id = $this->createPeriod();

        $this->postJson("/api/admin/schedule-periods/{$id}/generate")
            ->assertOk()
            ->assertJsonPath('message', 'Jadwal berhasil di-generate: 24 dari 25 slot terpenuhi, 1 slot kurang staff.')
            ->assertJsonPath('summary.understaffed', 1);

        $response = $this->getJson("/api/admin/schedule-periods/{$id}")->assertOk();
        $slots = collect($response->json('data.slots'));

        $this->assertCount(25, $slots);
        $this->assertSame('16:00', $slots->last()['start_time']);
        $this->assertSame('17:00', $slots->last()['end_time']);

        // Rabu 08–10: 9 dari 10 staff kuliah → hanya Nabila yang bisa ditugaskan.
        $wednesday = $slots->firstWhere(fn ($slot) => $slot['date'] === '2026-09-30' && $slot['start_time'] === '08:00');
        $this->assertTrue($wednesday['is_understaffed']);
        $this->assertSame(['Nabila Azzahra'], array_column($wednesday['assignments'], 'name'));

        $this->assertSame(49, ShiftAssignment::where('source', 'auto')->count());
        $this->assertCount(10, $response->json('meta.staff_hours'));
        $response->assertJsonPath('data.status', 'draft');
    }

    public function test_staff_tidak_pernah_ditugaskan_saat_ada_kuliah(): void
    {
        $id = $this->generatedPeriod();

        $conflicts = ShiftAssignment::query()
            ->join('shift_slots', 'shift_slots.id', '=', 'shift_assignments.shift_slot_id')
            ->join('class_schedules', function ($join) {
                $join->on('class_schedules.staff_profile_id', '=', 'shift_assignments.staff_profile_id')
                    ->whereRaw('class_schedules.day_of_week = WEEKDAY(shift_slots.date) + 1')
                    ->whereColumn('class_schedules.start_time', '<', 'shift_slots.end_time')
                    ->whereColumn('class_schedules.end_time', '>', 'shift_slots.start_time');
            })
            ->where('shift_slots.schedule_period_id', $id)
            ->count();

        $this->assertSame(0, $conflicts);
        $this->assertEmpty($this->getJson("/api/admin/schedule-periods/{$id}")->json('meta.warnings'));
    }

    public function test_log_proses_algoritma_tersimpan(): void
    {
        $id = $this->generatedPeriod();

        $response = $this->getJson("/api/admin/schedule-periods/{$id}/logs")->assertOk();
        $logs = collect($response->json('data'));

        $this->assertSame('queue', $logs[0]['action']);
        $this->assertStringStartsWith('Antrian awal: Andi Pratama → Salsabila Putri', $logs[0]['message']);
        $response->assertJsonPath('summary.understaffed', 1)->assertJsonPath('summary.assigned', 49);
        $this->assertGreaterThan(0, $response->json('summary.skip_reasons.class_conflict'));

        // Langkah pertama: Andi (posisi 1) dilewati karena kuliah Senin 08:00–10:30.
        $first = $logs[1];
        $this->assertSame('skipped', $first['action']);
        $this->assertSame(1, $first['pointer_position']);
        $this->assertSame('Andi Pratama', $first['staff']['name']);
        $this->assertSame('Bentrok jadwal kuliah', $first['reason_label']);
        $this->assertSame('08:00', $first['slot']['start_time']);
    }

    public function test_generate_ulang_menghasilkan_jadwal_yang_sama(): void
    {
        $id = $this->generatedPeriod();
        $first = $this->snapshot($id);

        $this->postJson("/api/admin/schedule-periods/{$id}/generate")->assertOk();

        $this->assertSame($first, $this->snapshot($id));
        $this->assertSame(25, ShiftSlot::where('schedule_period_id', $id)->count());
    }

    public function test_periode_berikutnya_melanjutkan_posisi_pointer(): void
    {
        $firstId = $this->generatedPeriod();
        $first = SchedulePeriod::findOrFail($firstId);

        $this->assertSame($first->end_pointer_staff_id, RoundRobinState::current()->next_staff_profile_id);

        $secondId = $this->createPeriod('2026-10-05', '2026-10-11');
        $this->postJson("/api/admin/schedule-periods/{$secondId}/generate")->assertOk();

        $this->assertSame($first->end_pointer_staff_id, SchedulePeriod::findOrFail($secondId)->start_pointer_staff_id);
    }

    public function test_menghapus_periode_draft_mengembalikan_pointer(): void
    {
        $id = $this->generatedPeriod();
        $startPointer = SchedulePeriod::findOrFail($id)->start_pointer_staff_id;

        $this->deleteJson("/api/admin/schedule-periods/{$id}")->assertOk();

        $this->assertSame($startPointer, RoundRobinState::current()->next_staff_profile_id);
        $this->assertSame(0, ShiftSlot::count());
    }

    public function test_periode_tidak_boleh_bertumpuk(): void
    {
        $this->createPeriod();

        $this->postJson('/api/admin/schedule-periods', ['start_date' => '2026-10-02', 'end_date' => '2026-10-08'])
            ->assertStatus(422)
            ->assertJsonPath('errors.start_date.0', 'Tanggal bertumpuk dengan periode 28 Sep 2026 – 4 Okt 2026 yang sudah ada.');
    }

    public function test_staff_hanya_melihat_jadwal_yang_sudah_dipublikasikan(): void
    {
        $id = $this->generatedPeriod();
        $admin = User::where('email', 'admin@logistik.test')->firstOrFail();
        $nabila = User::where('email', 'nabila.azzahra@logistik.test')->firstOrFail();
        $query = '/api/staff/shifts?from=2026-09-28&to=2026-10-04';

        Sanctum::actingAs($nabila);
        $this->getJson($query)->assertOk()->assertJsonCount(0, 'data');

        Sanctum::actingAs($admin);
        $this->postJson("/api/admin/schedule-periods/{$id}/publish")->assertOk();
        $this->postJson("/api/admin/schedule-periods/{$id}/generate")->assertStatus(422);

        Sanctum::actingAs($nabila);
        $shifts = $this->getJson($query)->assertOk()->json('data');
        $wednesday = collect($shifts)->firstWhere('date', '2026-09-30');

        $this->assertSame(ShiftAssignment::where('staff_profile_id', $nabila->staffProfile->id)->count(), count($shifts));
        $this->assertSame('08:00', $wednesday['start_time']);
        $this->assertSame([], $wednesday['teammates']);
    }

    public function test_admin_menambah_staff_manual_dengan_peringatan_bentrok_kuliah(): void
    {
        $id = $this->generatedPeriod();
        $slot = $this->slot($id, '2026-09-30', '08:00:00');

        $candidates = collect($this->getJson("/api/admin/shift-slots/{$slot->id}/candidates")->assertOk()->json('data'));
        $this->assertTrue($candidates->firstWhere('name', 'Nabila Azzahra')['is_assigned']);
        $this->assertCount(9, $candidates->filter(fn ($c) => collect($c['warnings'])->contains('type', 'class_conflict')));

        $andi = $this->staffId('andi.pratama@logistik.test');
        $this->postJson("/api/admin/shift-slots/{$slot->id}/assignments", ['staff_profile_id' => $andi])
            ->assertCreated()
            ->assertJsonPath('warnings.0.type', 'class_conflict')
            ->assertJsonPath('warnings.0.message', 'Ada kuliah Bahasa Inggris 07:30–10:00.');

        $this->assertSame(2, $slot->assignments()->count());
        $this->assertSame('manual', $slot->assignments()->where('staff_profile_id', $andi)->first()->source->value);

        // Staff yang sudah ada di slot tidak bisa ditambahkan dua kali.
        $this->postJson("/api/admin/shift-slots/{$slot->id}/assignments", ['staff_profile_id' => $andi])
            ->assertStatus(422);
    }

    public function test_admin_memindahkan_menukar_dan_menghapus_penugasan(): void
    {
        $id = $this->generatedPeriod();
        $mondayMorning = $this->slot($id, '2026-09-28', '08:00:00');
        $wednesdayMorning = $this->slot($id, '2026-09-30', '08:00:00');
        $fridayAfternoon = $this->slot($id, '2026-10-02', '16:00:00');

        // Pindah: Salsabila dari Senin pagi ke Rabu pagi (ia kuliah Pancasila Rabu 08:00–10:30).
        $salsabila = $mondayMorning->assignments()->where('staff_profile_id', $this->staffId('salsabila.putri@logistik.test'))->firstOrFail();
        $this->putJson("/api/admin/shift-assignments/{$salsabila->id}", ['shift_slot_id' => $wednesdayMorning->id])
            ->assertOk()
            ->assertJsonPath('warnings.0.type', 'class_conflict');
        $this->assertSame(1, $mondayMorning->assignments()->count());
        $this->assertSame(2, $wednesdayMorning->assignments()->count());

        // Tukar: staff Senin pagi ↔ staff Jumat sore.
        $a = $mondayMorning->assignments()->firstOrFail();
        $b = $fridayAfternoon->assignments()->firstOrFail();
        $this->postJson('/api/admin/shift-assignments/swap', ['first_assignment_id' => $a->id, 'second_assignment_id' => $b->id])
            ->assertOk();
        $this->assertSame($b->staff_profile_id, $a->fresh()->staff_profile_id);
        $this->assertSame($a->staff_profile_id, $b->fresh()->staff_profile_id);

        // Hapus.
        $this->deleteJson("/api/admin/shift-assignments/{$a->id}")->assertOk();
        $this->assertModelMissing($a);
    }

    public function test_tukar_ditolak_jika_staff_sudah_ada_di_slot_tujuan(): void
    {
        $id = $this->generatedPeriod();
        $wednesday = $this->slot($id, '2026-09-30', '10:00:00'); // Andi & Nabila
        $thursday = $this->slot($id, '2026-10-01', '14:00:00');  // Andi & Nabila

        $first = $wednesday->assignments()->where('staff_profile_id', $this->staffId('andi.pratama@logistik.test'))->firstOrFail();
        $second = $thursday->assignments()->where('staff_profile_id', $this->staffId('nabila.azzahra@logistik.test'))->firstOrFail();

        $this->postJson('/api/admin/shift-assignments/swap', ['first_assignment_id' => $first->id, 'second_assignment_id' => $second->id])
            ->assertStatus(422);
    }

    public function test_staff_nonaktif_tidak_masuk_antrian(): void
    {
        StaffProfile::whereHas('user', fn ($q) => $q->where('email', 'nabila.azzahra@logistik.test'))->update(['is_active' => false]);

        $id = $this->generatedPeriod();

        $this->assertSame(0, ShiftAssignment::where('staff_profile_id', $this->staffId('nabila.azzahra@logistik.test'))->count());
        // Tanpa Nabila, Rabu 08–10 tidak punya staff sama sekali.
        $this->assertSame(0, $this->slot($id, '2026-09-30', '08:00:00')->assignments()->count());
    }
}
