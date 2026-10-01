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

    private function shift(int $id, string $name, string $start, string $end): array
    {
        return ['id' => $id, 'name' => $name, 'start' => Time::toMinutes($start), 'end' => Time::toMinutes($end)];
    }

    private function posts(int $count = 3, int $capacity = 2): array
    {
        return array_map(fn (int $id) => ['id' => $id, 'name' => "Pos {$id}", 'capacity' => $capacity], range(1, $count));
    }

    private function twoShifts(): array
    {
        return [$this->shift(1, 'Shift Pagi', '08:00', '12:00'), $this->shift(2, 'Shift Siang', '13:00', '17:00')];
    }

    private function labels(array $slots): array
    {
        return array_map(fn ($s) => Time::format($s['start']).'-'.Time::format($s['end']).' '.$s['post_name'], $slots);
    }

    public function test_setiap_shift_dijaga_di_semua_pos(): void
    {
        $slots = SlotBuilder::build(Carbon::parse('2026-09-28'), Carbon::parse('2026-09-28'), $this->weekdays(), $this->twoShifts(), $this->posts());

        $this->assertSame([
            '08:00-12:00 Pos 1', '08:00-12:00 Pos 2', '08:00-12:00 Pos 3',
            '13:00-17:00 Pos 1', '13:00-17:00 Pos 2', '13:00-17:00 Pos 3',
        ], $this->labels($slots));
        $this->assertSame(2, $slots[0]['required']);
        $this->assertSame('Shift Pagi', $slots[0]['shift_name']);
        $this->assertSame(1, $slots[0]['shift_id']);
        $this->assertSame(1, $slots[0]['post_id']);
        $this->assertCount(6, array_unique(array_column($slots, 'key')));
    }

    public function test_hari_nonaktif_dalam_periode_dilewati(): void
    {
        // Senin 28 Sep s.d. Minggu 4 Okt 2026, hanya Senin–Jumat yang aktif.
        $slots = SlotBuilder::build(Carbon::parse('2026-09-28'), Carbon::parse('2026-10-04'), $this->weekdays(), $this->twoShifts(), $this->posts());

        $this->assertCount(30, $slots); // 5 hari × 2 shift × 3 pos
        $this->assertSame(['2026-09-28', '2026-09-29', '2026-09-30', '2026-10-01', '2026-10-02'], array_values(array_unique(array_column($slots, 'date'))));
    }

    public function test_shift_dipotong_ke_jam_operasional_dan_dilewati_jika_di_luar_jam_buka(): void
    {
        // Buka 08:00–15:00: shift siang dipendekkan; shift malam seluruhnya di luar jam buka.
        $shifts = [...$this->twoShifts(), $this->shift(3, 'Shift Malam', '17:00', '20:00')];
        $slots = SlotBuilder::build(Carbon::parse('2026-09-28'), Carbon::parse('2026-09-28'), $this->weekdays('08:00', '15:00'), $shifts, $this->posts(1));

        $this->assertSame(['08:00-12:00 Pos 1', '13:00-15:00 Pos 1'], $this->labels($slots));
    }

    public function test_kebutuhan_staff_mengikuti_kapasitas_pos_dan_shift_diurutkan_menurut_jam(): void
    {
        $shifts = array_reverse($this->twoShifts());
        $posts = [['id' => 1, 'name' => 'Gudang', 'capacity' => 1], ['id' => 2, 'name' => 'Loket', 'capacity' => 2]];
        $slots = SlotBuilder::build(Carbon::parse('2026-09-28'), Carbon::parse('2026-09-28'), $this->weekdays(), $shifts, $posts);

        $this->assertSame(['08:00-12:00 Gudang', '08:00-12:00 Loket', '13:00-17:00 Gudang', '13:00-17:00 Loket'], $this->labels($slots));
        $this->assertSame([1, 2, 1, 2], array_column($slots, 'required'));
    }
}
