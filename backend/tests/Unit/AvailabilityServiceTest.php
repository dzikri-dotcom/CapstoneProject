<?php

namespace Tests\Unit;

use App\Services\Scheduling\AvailabilityService;
use App\Support\Time;
use PHPUnit\Framework\TestCase;

class AvailabilityServiceTest extends TestCase
{
    /** Ubah ['08:00', '10:30'] menjadi rentang menit. */
    private function interval(string $start, string $end): array
    {
        return ['start' => Time::toMinutes($start), 'end' => Time::toMinutes($end)];
    }

    private function format(array $intervals): array
    {
        return array_map(fn ($i) => Time::format($i['start']).'-'.Time::format($i['end']), $intervals);
    }

    public function test_tanpa_kuliah_seluruh_jam_operasional_kosong(): void
    {
        $free = AvailabilityService::freeIntervals(480, 1020, []);

        $this->assertSame(['08:00-17:00'], $this->format($free));
    }

    public function test_jam_kosong_dikurangi_jadwal_kuliah(): void
    {
        $free = AvailabilityService::freeIntervals(480, 1020, [
            $this->interval('13:00', '15:30'),
            $this->interval('08:00', '10:30'),
        ]);

        $this->assertSame(['10:30-13:00', '15:30-17:00'], $this->format($free));
    }

    public function test_kuliah_di_luar_jam_operasional_dipotong(): void
    {
        $free = AvailabilityService::freeIntervals(480, 1020, [
            $this->interval('07:00', '09:00'), // mulai sebelum jam buka
            $this->interval('15:30', '18:00'), // selesai setelah jam tutup
            $this->interval('18:30', '20:00'), // sepenuhnya di luar jam operasional
        ]);

        $this->assertSame(['09:00-15:30'], $this->format($free));
    }

    public function test_kuliah_yang_beririsan_digabung(): void
    {
        $busy = AvailabilityService::mergeBusy(480, 1020, [
            $this->interval('08:00', '10:00'),
            $this->interval('09:30', '11:00'),
            $this->interval('11:00', '12:00'), // bersebelahan
        ]);

        $this->assertSame(['08:00-12:00'], $this->format($busy));
    }

    public function test_kuliah_yang_hanya_bersentuhan_dengan_slot_tidak_dianggap_bentrok(): void
    {
        $classes = [$this->interval('08:00', '10:00')];

        $this->assertTrue(AvailabilityService::isFree($classes, Time::toMinutes('10:00'), Time::toMinutes('12:00')));
        $this->assertFalse(AvailabilityService::isFree($classes, Time::toMinutes('09:59'), Time::toMinutes('12:00')));
    }

    public function test_slot_harus_kosong_di_seluruh_rentang(): void
    {
        // Kuliah 13:00–15:30 hanya menutupi sebagian slot 12:00–14:00, tetap dianggap bentrok.
        $classes = [$this->interval('13:00', '15:30')];

        $this->assertFalse(AvailabilityService::isFree($classes, Time::toMinutes('12:00'), Time::toMinutes('14:00')));
        $this->assertTrue(AvailabilityService::isFree($classes, Time::toMinutes('10:00'), Time::toMinutes('12:00')));
    }
}
