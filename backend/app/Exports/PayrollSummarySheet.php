<?php

namespace App\Exports;

use App\Models\PayrollDetail;
use App\Models\PayrollPeriod;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithColumnFormatting;
use Maatwebsite\Excel\Concerns\WithStrictNullComparison;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class PayrollSummarySheet implements FromArray, ShouldAutoSize, WithColumnFormatting, WithStrictNullComparison, WithStyles, WithTitle
{
    /** Baris judul sebelum tabel (judul, periode, status, pembulatan, baris kosong). */
    private const HEADER_ROW = 6;

    public function __construct(private readonly PayrollPeriod $period)
    {
    }

    public function title(): string
    {
        return 'Rekap';
    }

    public function array(): array
    {
        $details = $this->period->details->sortBy(fn (PayrollDetail $d) => $d->staffProfile->user->name)->values();
        $snapshot = $this->period->settings_snapshot ?? [];

        $rows = [
            ['Rekap Gaji Student Staff Logistik — Telkom University Surabaya'],
            ['Periode', $this->period->start_date->translatedFormat('j F Y').' s.d. '.$this->period->end_date->translatedFormat('j F Y')],
            ['Status', $this->period->status->label()],
            ['Pembulatan', PayrollSheetHelper::roundingLabel($snapshot)],
            [],
            ['No', 'Nama', 'NIM', 'Program Studi', 'Shift Hadir', 'Tidak Hadir', 'Total Jam Dibayar', 'Total Gaji (Rp)'],
        ];

        foreach ($details as $index => $detail) {
            $rows[] = [
                $index + 1,
                $detail->staffProfile->user->name,
                $detail->staffProfile->nim,
                $detail->staffProfile->study_program,
                $detail->total_shifts,
                $detail->items->whereNull('check_in_at')->count(),
                round($detail->total_minutes / 60, 2),
                (float) $detail->total_amount,
            ];
        }

        $rows[] = ['', 'TOTAL', '', '', $details->sum('total_shifts'), '', round($details->sum('total_minutes') / 60, 2), (float) $this->period->total_amount];

        return $rows;
    }

    public function columnFormats(): array
    {
        return [
            'C' => '@',
            'G' => '#,##0.00',
            'H' => PayrollSheetHelper::RUPIAH_FORMAT,
        ];
    }

    public function styles(Worksheet $sheet): ?array
    {
        $lastRow = self::HEADER_ROW + $this->period->details->count() + 1;

        return [
            1 => ['font' => ['bold' => true, 'size' => 14]],
            'A2:A4' => ['font' => ['bold' => true]],
            self::HEADER_ROW => PayrollSheetHelper::headerStyle(),
            $lastRow => ['font' => ['bold' => true]],
        ];
    }
}
