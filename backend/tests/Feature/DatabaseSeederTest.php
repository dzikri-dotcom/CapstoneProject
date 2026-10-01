<?php

namespace Tests\Feature;

use App\Enums\SchedulePeriodStatus;
use App\Enums\UserRole;
use App\Models\ClassSchedule;
use App\Models\HourlyRate;
use App\Models\OperationalHour;
use App\Models\Post;
use App\Models\SchedulePeriod;
use App\Models\Setting;
use App\Models\Shift;
use App\Models\StaffProfile;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class DatabaseSeederTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Dipanggil eksplisit: properti $seed hanya berlaku saat migrasi pertama
        // dalam satu proses test, sehingga tidak jalan jika test lain lebih dulu.
        $this->seed();
    }

    public function test_admin_dan_sepuluh_staff_aktif_dibuat(): void
    {
        $admin = User::where('email', 'admin@logistik.test')->firstOrFail();

        $this->assertSame(UserRole::Admin, $admin->role);
        $this->assertNull($admin->staffProfile);
        $this->assertSame(10, StaffProfile::active()->count());
        $this->assertSame(10, User::where('role', UserRole::Staff)->count());
    }

    public function test_pengaturan_bawaan_tersedia(): void
    {
        foreach (Setting::DEFAULTS as $key => $default) {
            $this->assertSame($default['value'], Setting::where('key', $key)->firstOrFail()->typedValue());
        }
    }

    public function test_shift_pos_dan_jam_operasional_sesuai_sop(): void
    {
        $this->assertSame(
            ['Shift 1 06:30–09:30', 'Shift 2 09:30–11:30', 'Shift 3 11:30–14:30', 'Shift 4 14:30–16:30', 'Shift 5 16:30–19:30'],
            Shift::active()->orderBy('start_time')->get()
                ->map(fn (Shift $s) => sprintf('%s %s–%s', $s->name, substr($s->start_time, 0, 5), substr($s->end_time, 0, 5)))->all(),
        );
        $this->assertSame(
            ['Pos 1' => 'Gedung A', 'Pos 2' => 'Gedung A', 'Pos 3' => 'Gedung B'],
            Post::active()->orderBy('id')->pluck('location', 'name')->all(),
        );
        $this->assertSame([2], Post::pluck('capacity')->unique()->values()->all());

        $monday = OperationalHour::where('day_of_week', 1)->firstOrFail();
        $this->assertSame(['06:30:00', '19:30:00', true], [$monday->open_time, $monday->close_time, $monday->is_active]);
        $this->assertFalse(OperationalHour::where('day_of_week', 7)->firstOrFail()->is_active);
        $this->assertSame(40, Setting::where('key', 'max_weekly_hours')->firstOrFail()->typedValue());
    }

    public function test_jadwal_dibentuk_per_shift_dan_pos_dengan_lokasinya(): void
    {
        Sanctum::actingAs(User::where('email', 'admin@logistik.test')->firstOrFail());

        $id = $this->postJson('/api/admin/schedule-periods', ['start_date' => '2026-10-05', 'end_date' => '2026-10-11'])->json('data.id');
        $this->postJson("/api/admin/schedule-periods/{$id}/generate")->assertOk();

        $slots = collect($this->getJson("/api/admin/schedule-periods/{$id}")->assertOk()->json('data.slots'));

        $this->assertCount(75, $slots); // 5 hari × 5 shift × 3 pos
        $this->assertSame(['06:30', '09:30', 'Shift 1'], [$slots[0]['start_time'], $slots[0]['end_time'], $slots[0]['shift']['name']]);
        $this->assertSame(['Gedung A', 'Gedung A', 'Gedung B'], $slots->take(3)->pluck('post.location')->all());
        $this->assertSame('19:30', $slots->last()['end_time']);
        $this->assertLessThanOrEqual(2, $slots->max(fn ($slot) => count($slot['assignments'])));
    }

    public function test_tarif_mengikuti_tanggal_berlaku(): void
    {
        $juli = HourlyRate::effectiveOn('2026-07-15')->pluck('rate', 'start_time');
        $september = HourlyRate::effectiveOn('2026-09-24')->pluck('rate', 'start_time');

        $this->assertCount(14, $juli); // 06.00–20.00, mencakup seluruh jam shift
        $this->assertSame(['06:00:00', '19:00:00'], [$juli->keys()->first(), $juli->keys()->last()]);
        $this->assertSame(10000, $juli['16:00:00']);
        $this->assertSame(11000, $september['08:00:00']);
        $this->assertSame(13500, $september['16:00:00']);
    }

    public function test_skenario_bentrok_rabu_pagi_hanya_satu_staff_kosong(): void
    {
        $busy = ClassSchedule::where('day_of_week', 3)
            ->where('start_time', '<', '10:00')
            ->where('end_time', '>', '08:00')
            ->distinct()
            ->count('staff_profile_id');

        $this->assertSame(9, $busy);
    }

    public function test_seeder_aman_dijalankan_ulang(): void
    {
        $this->seed();

        $this->assertSame(11, User::count());
        $this->assertSame(50, ClassSchedule::count());
        $this->assertSame(28, HourlyRate::count());
        $this->assertSame([5, 3], [Shift::count(), Post::count()]);
    }

    public function test_staff_yang_punya_riwayat_jadwal_tidak_bisa_dihapus(): void
    {
        $staff = StaffProfile::firstOrFail();
        $period = SchedulePeriod::create([
            'start_date' => '2026-09-28',
            'end_date' => '2026-10-02',
            'status' => SchedulePeriodStatus::Draft,
        ]);
        $slot = $period->shiftSlots()->create([
            'date' => '2026-09-28',
            'start_time' => '08:00',
            'end_time' => '10:00',
            'required_staff' => 2,
        ]);
        $slot->assignments()->create(['staff_profile_id' => $staff->id]);

        $this->expectException(QueryException::class);
        $staff->user->delete();
    }
}
