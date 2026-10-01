<?php

namespace Tests\Unit;

use App\Enums\SchedulerLogAction;
use App\Enums\SkipReason;
use App\Services\Scheduling\RoundRobinScheduler;
use App\Services\Scheduling\SchedulerResult;
use App\Services\Scheduling\SlotBuilder;
use App\Support\Time;
use Carbon\Carbon;
use PHPUnit\Framework\TestCase;

/**
 * Pengujian algoritma Round Robin tanpa database.
 * Tanggal 2026-09-28 adalah hari Senin.
 */
class RoundRobinSchedulerTest extends TestCase
{
    private const HOURS = 60;

    /**
     * @param  array<int, list<array{0:string, 1:string}>>  $classes  hari → [[mulai, selesai], ...]
     */
    private function staff(int $id, string $name, array $classes = []): array
    {
        return [
            'id' => $id,
            'name' => $name,
            'classes' => array_map(
                fn (array $list) => array_map(fn (array $c) => [
                    'start' => Time::toMinutes($c[0]),
                    'end' => Time::toMinutes($c[1]),
                    'course' => $c[2] ?? null,
                ], $list),
                $classes,
            ),
        ];
    }

    private function slot(string $date, string $start, string $end, int $required = 2): array
    {
        return [
            'key' => "{$date} {$start}",
            'date' => $date,
            'day_of_week' => Carbon::parse($date)->dayOfWeekIso,
            'start' => Time::toMinutes($start),
            'end' => Time::toMinutes($end),
            'required' => $required,
        ];
    }

    /** Slot pos jaga: beberapa pos pada jam yang sama. */
    private function postSlot(string $date, string $start, string $end, string $post, int $required = 2): array
    {
        return [...$this->slot($date, $start, $end, $required), 'key' => "{$date} {$start} {$post}", 'post_name' => $post];
    }

    /**
     * Slot seperti SlotBuilder: shift 2 jam sepanjang 08:00–17:00 dengan satu pos berisi 2 orang.
     *
     * @param  list<int>  $days  hari aktif (1 = Senin)
     */
    private function twoHourSlots(string $from, string $to, array $days): array
    {
        $shifts = array_map(fn (array $t) => [
            'id' => null, 'name' => "Shift {$t[0]}", 'start' => Time::toMinutes($t[0]), 'end' => Time::toMinutes($t[1]),
        ], [['08:00', '10:00'], ['10:00', '12:00'], ['12:00', '14:00'], ['14:00', '16:00'], ['16:00', '17:00']]);

        return SlotBuilder::build(
            Carbon::parse($from),
            Carbon::parse($to),
            array_fill_keys($days, ['open' => 8 * 60, 'close' => 17 * 60]),
            $shifts,
            [['id' => 1, 'name' => 'Pos 1', 'capacity' => 2]],
        );
    }

    private function scheduler(int $maxHours = 40): RoundRobinScheduler
    {
        return new RoundRobinScheduler($maxHours * self::HOURS);
    }

    /** Log langkah pengecekan staff untuk satu slot. */
    private function checksFor(SchedulerResult $result, string $slotKey): array
    {
        return array_values(array_filter(
            $result->logs,
            fn (array $log) => $log['slot_key'] === $slotKey && in_array($log['action'], [SchedulerLogAction::Assigned, SchedulerLogAction::Skipped], true),
        ));
    }

    public function test_staff_ditugaskan_bergiliran_mengikuti_antrian_melingkar(): void
    {
        $staff = [$this->staff(1, 'Andi'), $this->staff(2, 'Budi'), $this->staff(3, 'Citra')];
        $slots = [
            $this->slot('2026-09-28', '08:00', '10:00'),
            $this->slot('2026-09-28', '10:00', '12:00'),
            $this->slot('2026-09-28', '12:00', '14:00'),
        ];

        $result = $this->scheduler()->run($slots, $staff);

        // Pointer tidak kembali ke awal di setiap slot: A,B → C,A → B,C
        $this->assertSame([1, 2], $result->assignments['2026-09-28 08:00']);
        $this->assertSame([3, 1], $result->assignments['2026-09-28 10:00']);
        $this->assertSame([2, 3], $result->assignments['2026-09-28 12:00']);
        $this->assertSame(1, $result->endStaffId);
        $this->assertSame([], $result->understaffed);
    }

    public function test_staff_yang_bentrok_kuliah_dilewati_dan_pointer_maju(): void
    {
        $staff = [
            $this->staff(1, 'Andi', [1 => [['08:00', '10:30', 'Struktur Data']]]),
            $this->staff(2, 'Budi'),
            $this->staff(3, 'Citra'),
        ];

        $result = $this->scheduler()->run([$this->slot('2026-09-28', '08:00', '10:00')], $staff);

        $this->assertSame([2, 3], $result->assignments['2026-09-28 08:00']);

        $checks = $this->checksFor($result, '2026-09-28 08:00');
        $this->assertSame(SchedulerLogAction::Skipped, $checks[0]['action']);
        $this->assertSame(SkipReason::ClassConflict, $checks[0]['reason']);
        $this->assertSame(1, $checks[0]['pointer_position']);
        $this->assertSame('Andi dilewati: bentrok kuliah Struktur Data 08:00–10:30.', $checks[0]['message']);
        $this->assertSame(SchedulerLogAction::Assigned, $checks[1]['action']);
    }

    public function test_kuliah_sebagian_di_rentang_slot_tetap_dianggap_bentrok(): void
    {
        // Kuliah 13:00–15:30 hanya menutupi separuh slot 12:00–14:00.
        $staff = [$this->staff(1, 'Andi', [1 => [['13:00', '15:30']]]), $this->staff(2, 'Budi')];

        $result = $this->scheduler()->run([$this->slot('2026-09-28', '12:00', '14:00', 1)], $staff);

        $this->assertSame([2], $result->assignments['2026-09-28 12:00']);
    }

    public function test_kuliah_yang_hanya_bersentuhan_dengan_slot_tidak_bentrok(): void
    {
        $staff = [$this->staff(1, 'Andi', [1 => [['06:00', '08:00'], ['10:00', '12:00']]])];

        $result = $this->scheduler()->run([$this->slot('2026-09-28', '08:00', '10:00', 1)], $staff);

        $this->assertSame([1], $result->assignments['2026-09-28 08:00']);
    }

    public function test_staff_yang_mencapai_batas_jam_mingguan_dilewati(): void
    {
        $staff = [$this->staff(1, 'Andi'), $this->staff(2, 'Budi')];
        $slots = [
            $this->slot('2026-09-28', '08:00', '10:00', 1), // Andi 2 jam
            $this->slot('2026-09-28', '10:00', '12:00', 1), // Budi 2 jam
            $this->slot('2026-09-28', '12:00', '14:00', 1), // Andi 4 jam (= batas)
            $this->slot('2026-09-28', '14:00', '16:00', 1), // Budi 4 jam (= batas)
            $this->slot('2026-09-28', '16:00', '17:00', 1), // keduanya akan melebihi 4 jam
        ];

        $result = $this->scheduler(maxHours: 4)->run($slots, $staff);

        $this->assertSame([1], $result->assignments['2026-09-28 12:00']);
        $this->assertSame([2], $result->assignments['2026-09-28 14:00']);
        $this->assertSame([], $result->assignments['2026-09-28 16:00']);
        $this->assertSame(['2026-09-28 16:00' => 1], $result->understaffed);

        $reasons = array_column($this->checksFor($result, '2026-09-28 16:00'), 'reason');
        $this->assertSame([SkipReason::MaxWeeklyHours, SkipReason::MaxWeeklyHours], $reasons);
        $this->assertSame([1 => 240, 2 => 240], $result->minutesByStaff);
    }

    public function test_batas_jam_dihitung_per_minggu(): void
    {
        $staff = [$this->staff(1, 'Andi')];
        $slots = [
            $this->slot('2026-09-28', '08:00', '10:00', 1), // minggu ke-40
            $this->slot('2026-10-05', '08:00', '10:00', 1), // minggu ke-41
        ];

        $result = $this->scheduler(maxHours: 2)->run($slots, $staff);

        $this->assertSame([1], $result->assignments['2026-09-28 08:00']);
        $this->assertSame([1], $result->assignments['2026-10-05 08:00']);
    }

    public function test_jam_dari_periode_lain_di_minggu_yang_sama_ikut_dihitung(): void
    {
        $week = Carbon::parse('2026-09-28')->format('o-W');
        $staff = [$this->staff(1, 'Andi'), $this->staff(2, 'Budi')];

        $result = $this->scheduler(maxHours: 11)->run(
            [$this->slot('2026-09-28', '08:00', '10:00', 1)],
            $staff,
            initialWeeklyMinutes: [1 => [$week => 10 * self::HOURS]],
        );

        $this->assertSame([2], $result->assignments['2026-09-28 08:00']);
    }

    public function test_slot_ditandai_kurang_staff_jika_satu_putaran_tidak_cukup(): void
    {
        $staff = [
            $this->staff(1, 'Andi'),
            $this->staff(2, 'Budi', [1 => [['07:30', '10:00']]]),
            $this->staff(3, 'Citra', [1 => [['08:00', '10:30']]]),
        ];

        $result = $this->scheduler()->run([$this->slot('2026-09-28', '08:00', '10:00', 2)], $staff);

        $this->assertSame([1], $result->assignments['2026-09-28 08:00']);
        $this->assertSame(['2026-09-28 08:00' => 1], $result->understaffed);
        $this->assertCount(3, $this->checksFor($result, '2026-09-28 08:00')); // setiap staff dicek tepat sekali

        $last = $result->logs[array_key_last($result->logs)];
        $this->assertSame(SchedulerLogAction::Understaffed, $last['action']);
        $this->assertSame(1, $last['meta']['missing']);
    }

    public function test_antrian_dimulai_dari_pointer_periode_sebelumnya(): void
    {
        $staff = [$this->staff(1, 'A'), $this->staff(2, 'B'), $this->staff(3, 'C'), $this->staff(4, 'D')];

        $result = $this->scheduler()->run([$this->slot('2026-09-28', '08:00', '10:00')], $staff, startStaffId: 3);

        $this->assertSame([3, 4], $result->assignments['2026-09-28 08:00']);
        $this->assertSame([3, 4, 1, 2], $result->logs[0]['meta']['queue']);
        $this->assertSame('Antrian awal: C → D → A → B', $result->logs[0]['message']);
        $this->assertSame(1, $result->endStaffId);
    }

    public function test_pointer_ke_staff_nonaktif_dilanjutkan_ke_staff_berikutnya(): void
    {
        // Staff ID 3 sudah nonaktif sehingga tidak ada di daftar.
        $staff = [$this->staff(1, 'A'), $this->staff(2, 'B'), $this->staff(4, 'D')];

        $fromInactive = $this->scheduler()->run([$this->slot('2026-09-28', '08:00', '10:00', 1)], $staff, startStaffId: 3);
        $beyondLast = $this->scheduler()->run([$this->slot('2026-09-28', '08:00', '10:00', 1)], $staff, startStaffId: 9);

        $this->assertSame([4, 1, 2], $fromInactive->logs[0]['meta']['queue']);
        $this->assertSame([1, 2, 4], $beyondLast->logs[0]['meta']['queue']);
    }

    public function test_giliran_berlanjut_adil_antar_periode(): void
    {
        $staff = [$this->staff(1, 'A'), $this->staff(2, 'B'), $this->staff(3, 'C')];
        $scheduler = $this->scheduler();

        $week1 = $scheduler->run([
            $this->slot('2026-09-28', '08:00', '10:00', 1),
            $this->slot('2026-09-28', '10:00', '12:00', 1),
        ], $staff);
        $week2 = $scheduler->run([
            $this->slot('2026-10-05', '08:00', '10:00', 1),
            $this->slot('2026-10-05', '10:00', '12:00', 1),
        ], $staff, startStaffId: $week1->endStaffId);

        // Minggu 1: A, B. Minggu 2 melanjutkan dari C (bukan kembali ke A).
        $this->assertSame(3, $week1->endStaffId);
        $this->assertSame([3], $week2->assignments['2026-10-05 08:00']);
        $this->assertSame([1], $week2->assignments['2026-10-05 10:00']);
    }

    public function test_distribusi_shift_merata_jika_ketersediaan_sama(): void
    {
        $staff = array_map(fn (int $id) => $this->staff($id, "Staff {$id}"), range(1, 10));
        $slots = $this->twoHourSlots('2026-09-28', '2026-10-02', range(1, 5));

        $result = $this->scheduler()->run($slots, $staff);

        $shiftCounts = array_count_values(array_merge(...array_values($result->assignments)));
        $this->assertCount(25, $slots);
        $this->assertSame([], $result->understaffed);
        $this->assertCount(10, $shiftCounts);
        $this->assertSame(5, min($shiftCounts)); // 50 penugasan / 10 staff = 5 shift per staff
        $this->assertSame(5, max($shiftCounts));
    }

    public function test_hasil_selalu_sama_untuk_input_yang_sama(): void
    {
        $staff = [
            $this->staff(1, 'A', [1 => [['08:00', '10:30']]]),
            $this->staff(2, 'B'),
            $this->staff(3, 'C', [1 => [['13:00', '15:00']]]),
        ];
        $slots = $this->twoHourSlots('2026-09-28', '2026-09-28', [1]);

        $first = $this->scheduler(6)->run($slots, $staff, startStaffId: 2);
        $second = $this->scheduler(6)->run($slots, $staff, startStaffId: 2);

        $this->assertSame($first->assignments, $second->assignments);
        $this->assertSame($first->logs, $second->logs);
    }

    public function test_batas_jam_mingguan_khusus_staff_menggantikan_batas_umum(): void
    {
        // Batas umum 4 jam/minggu; Budi punya batas khusus 8 jam, Citra hanya 2 jam.
        $staff = [
            $this->staff(1, 'Andi'),
            [...$this->staff(2, 'Budi'), 'max_minutes' => 8 * self::HOURS],
            [...$this->staff(3, 'Citra'), 'max_minutes' => 2 * self::HOURS],
        ];
        $slots = [
            $this->slot('2026-09-28', '08:00', '12:00', 3),
            $this->slot('2026-09-29', '08:00', '12:00', 3),
        ];

        $result = $this->scheduler(4)->run($slots, $staff);

        $this->assertSame([1, 2], $result->assignments['2026-09-28 08:00']); // Citra: 4 jam > batas 2 jam
        $this->assertSame([2], $result->assignments['2026-09-29 08:00']);    // Andi sudah 4 jam; Budi masih boleh sampai 8 jam

        $citra = $this->checksFor($result, '2026-09-28 08:00')[2];
        $this->assertSame(SkipReason::MaxWeeklyHours, $citra['reason']);
        $this->assertSame('Citra dilewati: 0 menit + 4 jam melebihi batas 2 jam per minggu.', $citra['message']);
    }

    public function test_tiga_pos_pada_shift_yang_sama_diisi_staff_berbeda(): void
    {
        $staff = array_map(fn (int $id) => $this->staff($id, "Staff {$id}"), range(1, 6));
        $slots = [
            $this->postSlot('2026-09-28', '08:00', '12:00', 'Pos 1'),
            $this->postSlot('2026-09-28', '08:00', '12:00', 'Pos 2'),
            $this->postSlot('2026-09-28', '08:00', '12:00', 'Pos 3'),
        ];

        $result = $this->scheduler()->run($slots, $staff);

        // Giliran berlanjut dari pos ke pos: 2 orang per pos, tidak ada yang dobel.
        $this->assertSame([[1, 2], [3, 4], [5, 6]], array_values($result->assignments));
        $this->assertSame([], $result->understaffed);
    }

    public function test_staff_tidak_ditugaskan_di_dua_pos_pada_jam_yang_sama(): void
    {
        // 4 staff untuk 3 pos × 2 orang: pos ke-3 tidak bisa mengambil lagi staff dari pos 1 & 2.
        $staff = array_map(fn (int $id) => $this->staff($id, "Staff {$id}"), range(1, 4));
        $slots = [
            $this->postSlot('2026-09-28', '08:00', '12:00', 'Pos 1'),
            $this->postSlot('2026-09-28', '08:00', '12:00', 'Pos 2'),
            $this->postSlot('2026-09-28', '08:00', '12:00', 'Pos 3'),
            $this->postSlot('2026-09-28', '13:00', '17:00', 'Pos 1'),
        ];

        $result = $this->scheduler()->run($slots, $staff);

        $this->assertSame([1, 2], $result->assignments['2026-09-28 08:00 Pos 1']);
        $this->assertSame([3, 4], $result->assignments['2026-09-28 08:00 Pos 2']);
        $this->assertSame([], $result->assignments['2026-09-28 08:00 Pos 3']);
        $this->assertSame(['2026-09-28 08:00 Pos 3' => 2], $result->understaffed);

        $checks = $this->checksFor($result, '2026-09-28 08:00 Pos 3');
        $this->assertCount(4, $checks);
        $this->assertSame(SkipReason::ShiftConflict, $checks[0]['reason']);
        $this->assertSame('Staff 1 dilewati: sudah bertugas di Pos 1 08:00–12:00.', $checks[0]['message']);

        // Shift berikutnya tidak beririsan, jadi staff yang sama boleh bertugas lagi.
        $this->assertSame([1, 2], $result->assignments['2026-09-28 13:00 Pos 1']);
    }

    public function test_tanpa_staff_aktif_semua_slot_kurang_staff(): void
    {
        $slots = [$this->slot('2026-09-28', '08:00', '10:00'), $this->slot('2026-09-28', '10:00', '12:00')];

        $result = $this->scheduler()->run($slots, []);

        $this->assertSame(['2026-09-28 08:00' => 2, '2026-09-28 10:00' => 2], $result->understaffed);
        $this->assertNull($result->startStaffId);
        $this->assertNull($result->endStaffId);
    }
}
