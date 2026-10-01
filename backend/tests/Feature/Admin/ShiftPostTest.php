<?php

namespace Tests\Feature\Admin;

use App\Models\Post;
use App\Models\SchedulePeriod;
use App\Models\Setting;
use App\Models\Shift;
use App\Models\ShiftAssignment;
use App\Models\ShiftSlot;
use App\Models\StaffProfile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Fitur shift & pos jaga diuji dengan konfigurasi sederhana yang dipasang di setUp:
 * Shift Pagi 08:00–12:00, Shift Siang 13:00–17:00, 3 pos × 2 orang, batas 12 jam per minggu.
 * (Data bawaan sesuai SOP — 5 shift, pos per gedung — diuji di DatabaseSeederTest.)
 * Periode uji: Senin 28 Sep – Minggu 4 Okt 2026 (data seeder: 10 staff).
 */
class ShiftPostTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo('2026-09-24 10:00:00');
        $this->seed();
        $this->useTwoShiftsAndThreePosts();
        Sanctum::actingAs(User::where('email', 'admin@logistik.test')->firstOrFail());
    }

    private function useTwoShiftsAndThreePosts(): void
    {
        Shift::query()->delete();
        Post::query()->delete();

        Shift::create(['name' => 'Shift Pagi', 'start_time' => '08:00', 'end_time' => '12:00']);
        Shift::create(['name' => 'Shift Siang', 'start_time' => '13:00', 'end_time' => '17:00']);
        foreach (['Pos 1', 'Pos 2', 'Pos 3'] as $name) {
            Post::create(['name' => $name, 'capacity' => 2]);
        }
        Setting::query()->updateOrCreate(['key' => 'max_weekly_hours'], ['value' => '12', 'type' => 'integer']);
    }

    private function generatedPeriod(): int
    {
        $id = $this->postJson('/api/admin/schedule-periods', ['start_date' => '2026-09-28', 'end_date' => '2026-10-04'])
            ->assertCreated()
            ->json('data.id');
        $this->postJson("/api/admin/schedule-periods/{$id}/generate")->assertOk();

        return $id;
    }

    public function test_daftar_shift_dan_pos(): void
    {
        $this->getJson('/api/admin/shifts')
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.name', 'Shift Pagi')
            ->assertJsonPath('data.0.start_time', '08:00')
            ->assertJsonPath('data.0.end_time', '12:00')
            ->assertJsonPath('data.0.duration_minutes', 240);

        $this->getJson('/api/admin/posts')
            ->assertOk()
            ->assertJsonCount(3, 'data')
            ->assertJsonPath('data.2.name', 'Pos 3')
            ->assertJsonPath('data.2.capacity', 2);
    }

    public function test_admin_mengelola_shift(): void
    {
        $id = $this->postJson('/api/admin/shifts', ['name' => 'Shift Sore', 'start_time' => '17:00', 'end_time' => '20:00'])
            ->assertCreated()
            ->assertJsonPath('message', 'Shift Sore berhasil ditambahkan.')
            ->assertJsonPath('data.is_active', true)
            ->json('data.id');

        $this->putJson("/api/admin/shifts/{$id}", ['name' => 'Shift Malam', 'start_time' => '17:30', 'end_time' => '20:00', 'is_active' => false])
            ->assertOk()
            ->assertJsonPath('data.name', 'Shift Malam')
            ->assertJsonPath('data.start_time', '17:30')
            ->assertJsonPath('data.is_active', false);

        $this->deleteJson("/api/admin/shifts/{$id}")->assertOk();
        $this->assertSame(2, Shift::count());
    }

    public function test_validasi_shift(): void
    {
        $this->postJson('/api/admin/shifts', ['name' => '', 'start_time' => '12:00', 'end_time' => '10:00'])
            ->assertStatus(422)
            ->assertJsonPath('errors.name.0', 'Nama shift wajib diisi.')
            ->assertJsonPath('errors.end_time.0', 'Jam selesai harus setelah jam mulai.');

        // Beririsan dengan Shift Pagi 08:00–12:00.
        $this->postJson('/api/admin/shifts', ['name' => 'Shift Tengah', 'start_time' => '11:00', 'end_time' => '14:00'])
            ->assertStatus(422)
            ->assertJsonPath('errors.start_time.0', 'Jam shift beririsan dengan Shift Pagi (08:00–12:00).');

        // Bersentuhan (12:00–13:00) tidak dianggap beririsan, dan shift boleh diubah tanpa bentrok dengan dirinya sendiri.
        $this->postJson('/api/admin/shifts', ['name' => 'Shift Istirahat', 'start_time' => '12:00', 'end_time' => '13:00'])->assertCreated();
        $pagi = Shift::where('name', 'Shift Pagi')->firstOrFail();
        $this->putJson("/api/admin/shifts/{$pagi->id}", ['name' => 'Shift Pagi', 'start_time' => '07:30', 'end_time' => '12:00'])->assertOk();
    }

    public function test_admin_mengelola_pos_dengan_maksimal_dua_penjaga(): void
    {
        $this->postJson('/api/admin/posts', ['name' => 'Pos 4', 'capacity' => 3])
            ->assertStatus(422)
            ->assertJsonPath('errors.capacity.0', 'Satu pos dijaga 1 sampai 2 orang.');

        $this->postJson('/api/admin/posts', ['name' => 'Pos 1', 'capacity' => 2])
            ->assertStatus(422)
            ->assertJsonPath('errors.name.0', 'Nama pos sudah digunakan.');

        $id = $this->postJson('/api/admin/posts', ['name' => 'Gudang', 'location' => 'Gedung B', 'capacity' => 1])
            ->assertCreated()
            ->assertJsonPath('data.location', 'Gedung B')
            ->assertJsonPath('data.capacity', 1)
            ->json('data.id');

        $this->putJson("/api/admin/posts/{$id}", ['name' => 'Gudang Utama', 'capacity' => 2, 'is_active' => false])
            ->assertOk()
            ->assertJsonPath('data.name', 'Gudang Utama')
            ->assertJsonPath('data.is_active', false);

        $this->deleteJson("/api/admin/posts/{$id}")->assertOk();
        $this->assertSame(3, Post::count());
    }

    public function test_staff_tidak_bisa_mengelola_shift_dan_pos(): void
    {
        Sanctum::actingAs(User::where('email', 'andi.pratama@logistik.test')->firstOrFail());

        $this->getJson('/api/admin/shifts')->assertForbidden();
        $this->postJson('/api/admin/posts', ['name' => 'Pos 9', 'capacity' => 2])->assertForbidden();
    }

    public function test_generate_membentuk_slot_per_shift_dan_pos(): void
    {
        $id = $this->generatedPeriod();

        $slots = collect($this->getJson("/api/admin/schedule-periods/{$id}")->assertOk()->json('data.slots'));

        $this->assertCount(30, $slots); // 5 hari × 2 shift × 3 pos
        $this->assertSame(['Pos 1', 'Pos 2', 'Pos 3'], $slots->take(3)->pluck('post.name')->all());
        $this->assertSame(['Shift Pagi'], $slots->take(3)->pluck('shift.name')->unique()->values()->all());
        $this->assertSame(['08:00', '12:00'], [$slots[0]['start_time'], $slots[0]['end_time']]);
        $this->assertSame([2], $slots->pluck('required_staff')->unique()->values()->all());

        // Tidak ada pos yang diisi lebih dari 2 orang.
        $this->assertLessThanOrEqual(2, $slots->max(fn ($slot) => count($slot['assignments'])));
    }

    public function test_staff_tidak_pernah_berada_di_dua_pos_pada_shift_yang_sama(): void
    {
        $id = $this->generatedPeriod();

        $double = ShiftAssignment::query()
            ->join('shift_slots', 'shift_slots.id', '=', 'shift_assignments.shift_slot_id')
            ->where('shift_slots.schedule_period_id', $id)
            ->selectRaw('staff_profile_id, date, start_time, count(*) as total')
            ->groupBy('staff_profile_id', 'date', 'start_time')
            ->having('total', '>', 1)
            ->get();

        $this->assertCount(0, $double);

        $logs = $this->getJson("/api/admin/schedule-periods/{$id}/logs")->assertOk();
        $this->assertGreaterThan(0, $logs->json('summary.skip_reasons.shift_conflict'));
        $this->assertNotNull(collect($logs->json('data'))->firstWhere('reason_label', 'Sudah bertugas di pos lain pada jam yang sama'));
    }

    public function test_hanya_shift_dan_pos_aktif_yang_dijadwalkan(): void
    {
        Shift::where('name', 'Shift Siang')->update(['is_active' => false]);
        Post::where('name', 'Pos 3')->update(['is_active' => false]);
        Post::where('name', 'Pos 2')->update(['capacity' => 1]);

        $id = $this->generatedPeriod();
        $slots = ShiftSlot::where('schedule_period_id', $id)->get();

        $this->assertCount(10, $slots); // 5 hari × 1 shift × 2 pos
        $this->assertSame(['Pos 1', 'Pos 2'], $slots->pluck('post_name')->unique()->values()->all());
        $this->assertSame(1, $slots->firstWhere('post_name', 'Pos 2')->required_staff);
    }

    public function test_generate_ditolak_jika_belum_ada_shift_atau_pos_aktif(): void
    {
        Post::query()->update(['is_active' => false]);
        $id = $this->postJson('/api/admin/schedule-periods', ['start_date' => '2026-09-28', 'end_date' => '2026-10-04'])->json('data.id');

        $this->postJson("/api/admin/schedule-periods/{$id}/generate")
            ->assertStatus(422)
            ->assertJsonPath('errors.period.0', 'Belum ada shift atau pos jaga yang aktif. Atur terlebih dahulu di halaman Pengaturan.');
    }

    public function test_admin_tidak_bisa_menaruh_staff_di_dua_pos_pada_jam_yang_sama(): void
    {
        $id = $this->generatedPeriod();
        $monday = ShiftSlot::where('schedule_period_id', $id)->whereDate('date', '2026-09-28')->where('start_time', '08:00:00')->orderBy('id')->get();
        $posOne = $monday[0];
        $posThree = $monday[2];
        $staffId = $posOne->assignments()->firstOrFail()->staff_profile_id;
        $name = StaffProfile::findOrFail($staffId)->user->name;

        $candidate = collect($this->getJson("/api/admin/shift-slots/{$posThree->id}/candidates")->assertOk()->json('data'))
            ->firstWhere('staff_profile_id', $staffId);
        $this->assertSame('08:00–12:00 · Pos 1', $candidate['busy_in']);

        $this->postJson("/api/admin/shift-slots/{$posThree->id}/assignments", ['staff_profile_id' => $staffId])
            ->assertStatus(422)
            ->assertJsonPath('errors.staff_profile_id.0', "{$name} sudah ditugaskan di slot Senin, 28 Sep 08:00–12:00 · Pos 1.");
    }

    public function test_pos_yang_sudah_penuh_tidak_bisa_ditambah_staff(): void
    {
        $id = $this->generatedPeriod();
        $full = ShiftSlot::where('schedule_period_id', $id)->has('assignments', '=', 2)->orderBy('id')->firstOrFail();
        $outsider = StaffProfile::whereNotIn('id', $full->assignments()->pluck('staff_profile_id'))->firstOrFail();

        $this->postJson("/api/admin/shift-slots/{$full->id}/assignments", ['staff_profile_id' => $outsider->id])
            ->assertStatus(422)
            ->assertJsonPath('errors.shift_slot_id.0', "{$full->post_name} sudah penuh (maksimal 2 orang).");

        $this->assertSame(2, $full->assignments()->count());
    }

    public function test_tukar_staff_antar_pos_pada_shift_yang_sama(): void
    {
        $id = $this->generatedPeriod();
        $monday = ShiftSlot::where('schedule_period_id', $id)->whereDate('date', '2026-09-28')->where('start_time', '13:00:00')->orderBy('id')->get();
        $a = $monday[0]->assignments()->firstOrFail();
        $b = $monday[1]->assignments()->firstOrFail();

        $this->postJson('/api/admin/shift-assignments/swap', ['first_assignment_id' => $a->id, 'second_assignment_id' => $b->id])->assertOk();

        $this->assertSame($b->staff_profile_id, $a->fresh()->staff_profile_id);
        $this->assertSame($a->staff_profile_id, $b->fresh()->staff_profile_id);
    }

    public function test_jadwal_lama_tetap_terbaca_setelah_shift_dan_pos_dihapus(): void
    {
        $id = $this->generatedPeriod();

        $this->deleteJson('/api/admin/shifts/'.Shift::where('name', 'Shift Pagi')->value('id'))->assertOk();
        $this->deleteJson('/api/admin/posts/'.Post::where('name', 'Pos 1')->value('id'))->assertOk();

        $first = $this->getJson("/api/admin/schedule-periods/{$id}")->assertOk()->json('data.slots.0');
        $this->assertSame(30, SchedulePeriod::findOrFail($id)->shiftSlots()->count());
        $this->assertSame(['id' => null, 'name' => 'Shift Pagi'], $first['shift']);
        $this->assertSame(['id' => null, 'name' => 'Pos 1', 'location' => null], $first['post']);
    }

    public function test_staff_melihat_pos_jaganya(): void
    {
        $id = $this->generatedPeriod();
        $this->postJson("/api/admin/schedule-periods/{$id}/publish")->assertOk();
        $assignment = ShiftAssignment::query()->with('shiftSlot', 'staffProfile.user')->orderBy('id')->firstOrFail();

        Sanctum::actingAs($assignment->staffProfile->user);
        $shift = collect($this->getJson('/api/staff/shifts?from=2026-09-28&to=2026-10-04')->assertOk()->json('data'))->firstWhere('id', $assignment->id);

        $this->assertSame($assignment->shiftSlot->post_name, $shift['post_name']);
        $this->assertSame('Shift Pagi', $shift['shift_name']);
        $this->assertLessThanOrEqual(1, count($shift['teammates'])); // rekan satu pos, maksimal 1 orang lain
    }
}
