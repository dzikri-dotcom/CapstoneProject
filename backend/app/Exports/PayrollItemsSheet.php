<?php

namespace App\Exports;

use App\Enums\DayOfWeek;
use App\Models\PayrollDetail;
use App\Models\PayrollItem;
use App\Models\PayrollPeriod;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithColumnFormatting;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStrictNullComparison;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/**
 * Rincian per shift: jam hadir, menit yang dibayar, dan pecahan per slot jam × tarif.
 */
class PayrollItemsSheet implements FromArray, ShouldAutoSize, WithColumnFormatting, WithHeadings, WithStrictNullComparison, WithStyles, WithTitle
{
    public function __construct(private readonly PayrollPeriod $period)
    {
    }

    public function title(): string
    {
        return 'Rincian';
    }

    public function headings(): array
    {
        return [
            'Nama', 'NIM', 'Tanggal', 'Hari', 'Shift', 'Check-in', 'Check-out',
            'Menit Hadir', 'Menit Dibayar', 'Rincian (menit × tarif/jam)', 'Nominal (Rp)',
        ];
    }

    public function array(): array
    {
        $rows = [];

        foreach ($this->period->details->sortBy(fn (PayrollDetail $d) => $d->staffProfile->user->name) as $detail) {
            foreach ($detail->items as $item) {
                /** @var PayrollItem $item */
                $segments = collect($item->breakdown['segments'] ?? [])
                    ->map(fn (array $s) => sprintf('%s–%s: %d mnt × Rp%s', $s['from'], $s['to'], $s['minutes'], number_format($s['rate'], 0, ',', '.')))
                    ->implode('; ');

                $rows[] = [
                    $detail->staffProfile->user->name,
                    $detail->staffProfile->nim,
                    $item->date->toDateString(),
                    DayOfWeek::from($item->date->dayOfWeekIso)->label(),
                    substr($item->shift_start, 0, 5).'–'.substr($item->shift_end, 0, 5),
                    $item->check_in_at?->format('H:i') ?? 'Tidak hadir',
                    $item->check_out_at?->format('H:i') ?? '-',
                    $item->actual_minutes,
                    $item->paid_minutes,
                    $segments ?: '-',
                    (float) $item->amount,
                ];
            }
        }

        return $rows;
    }

    public function columnFormats(): array
    {
        return [
            'B' => '@',
            'K' => PayrollSheetHelper::RUPIAH_FORMAT,
        ];
    }

    public function styles(Worksheet $sheet): ?array
    {
        return [1 => PayrollSheetHelper::headerStyle()];
    }
}
