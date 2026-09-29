<?php

namespace Tests\Feature;

use App\Enums\SchedulePeriodStatus;
use App\Enums\UserRole;
use App\Models\ClassSchedule;
use App\Models\HourlyRate;
use App\Models\SchedulePeriod;
use App\Models\Setting;
use App\Models\StaffProfile;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
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

    public function test_tarif_mengikuti_tanggal_berlaku(): void
    {
        $juli = HourlyRate::effectiveOn('2026-07-15')->pluck('rate', 'start_time');
        $september = HourlyRate::effectiveOn('2026-09-24')->pluck('rate', 'start_time');

        $this->assertCount(9, $juli);
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
        $this->assertSame(18, HourlyRate::count());
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
