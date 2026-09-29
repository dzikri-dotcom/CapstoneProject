<?php

namespace App\Exports;

use Carbon\Carbon;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithColumnFormatting;
use Maatwebsite\Excel\Concerns\WithStrictNullComparison;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/**
 * Rekap kehadiran per staff pada satu rentang tanggal (hasil ReportService::attendance()).
 */
class AttendanceRecapExport implements FromArray, ShouldAutoSize, WithColumnFormatting, WithStrictNullComparison, WithStyles, WithTitle
{
    private const HEADER_ROW = 4;

    public function __construct(private readonly array $report)
    {
    }

    public function title(): string
    {
        return 'Rekap Kehadiran';
    }

    public function array(): array
    {
        $rows = [
            ['Rekap Kehadiran Student Staff Logistik — Telkom University Surabaya'],
            ['Periode', Carbon::parse($this->report['from'])->translatedFormat('j F Y').' s.d. '.Carbon::parse($this->report['to'])->translatedFormat('j F Y')],
            [],
            ['No', 'Nama', 'NIM', 'Jumlah Shift', 'Tepat Waktu', 'Terlambat', 'Tidak Hadir', 'Belum Check-out', 'Dikoreksi Admin', 'Jam Hadir', 'Tingkat Kehadiran (%)'],
        ];

        foreach ($this->report['staff'] as $index => $row) {
            $rows[] = [
                $index + 1, $row['name'], $row['nim'], $row['shifts'], $row['on_time'], $row['late'], $row['absent'],
                $row['missing_checkout'], $row['corrected'], round($row['worked_minutes'] / 60, 2), $row['attendance_rate'],
            ];
        }

        $t = $this->report['totals'];
        $rows[] = ['', 'TOTAL', '', $t['shifts'], $t['on_time'], $t['late'], $t['absent'], $t['missing_checkout'], '', round($t['worked_minutes'] / 60, 2), $t['attendance_rate']];

        return $rows;
    }

    public function columnFormats(): array
    {
        return ['C' => '@', 'J' => '#,##0.00', 'K' => '0.0'];
    }

    public function styles(Worksheet $sheet): ?array
    {
        return [
            1 => ['font' => ['bold' => true, 'size' => 14]],
            'A2' => ['font' => ['bold' => true]],
            self::HEADER_ROW => PayrollSheetHelper::headerStyle(),
            self::HEADER_ROW + count($this->report['staff']) + 1 => ['font' => ['bold' => true]],
        ];
    }
}
