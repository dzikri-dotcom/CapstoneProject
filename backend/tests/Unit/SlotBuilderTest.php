<?php

namespace Tests\Unit;

use App\Services\Scheduling\SlotBuilder;
use App\Support\Time;
use Carbon\Carbon;
use PHPUnit\Framework\TestCase;

class SlotBuilderTest extends TestCase
{
    private function weekdays(string $open = '08:00', string $close = '17:00'): array
    {
        return array_fill_keys(range(1, 5), ['open' => Time::toMinutes($open), 'close' => Time::toMinutes($close)]);
    }

    private function times(array $slots): array
    {
        return array_map(fn ($s) => Time::format($s['start']).'-'.Time::format($s['end']), $slots);
    }

    public function test_slot_terakhir_dipendekkan_sampai_jam_tutup(): void
    {
        $slots = SlotBuilder::build(Carbon::parse('2026-09-28'), Carbon::parse('2026-09-28'), $this->weekdays(), 120, 2);

        $this->assertSame(['08:00-10:00', '10:00-12:00', '12:00-14:00', '14:00-16:00', '16:00-17:00'], $this->times($slots));
        $this->assertSame(2, $slots[0]['required']);
        $this->assertSame('2026-09-28 08:00', $slots[0]['key']);
    }

    public function test_hari_nonaktif_dalam_periode_dilewati(): void
    {
        // Senin 28 Sep s.d. Minggu 4 Okt 2026, hanya Senin–Jumat yang aktif.
        $slots = SlotBuilder::build(Carbon::parse('2026-09-28'), Carbon::parse('2026-10-04'), $this->weekdays(), 120, 2);

        $this->assertCount(25, $slots);
        $this->assertSame(['2026-09-28', '2026-09-29', '2026-09-30', '2026-10-01', '2026-10-02'], array_values(array_unique(array_column($slots, 'date'))));
    }

    public function test_durasi_shift_bisa_diubah(): void
    {
        $slots = SlotBuilder::build(Carbon::parse('2026-09-28'), Carbon::parse('2026-09-28'), $this->weekdays(), 90, 1);

        $this->assertSame(
            ['08:00-09:30', '09:30-11:00', '11:00-12:30', '12:30-14:00', '14:00-15:30', '15:30-17:00'],
            $this->times($slots),
        );
    }
}
