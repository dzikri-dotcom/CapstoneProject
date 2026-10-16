<?php

namespace App\Exports;

final class PayrollSheetHelper
{
    public const RUPIAH_FORMAT = '"Rp"#,##0';

    public static function headerStyle(): array
    {
        return [
            'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
            'fill' => ['fillType' => 'solid', 'startColor' => ['rgb' => 'C8252C']],
        ];
    }

    public static function roundingLabel(array $snapshot): string
    {
        // Periode lama belum menyimpan aturan potongan: saat itu gaji selalu mengikuti menit hadir.
        if (($snapshot['salary_deduction_enabled'] ?? null) === false) {
            return 'Tanpa potongan (hadir dibayar penuh sepanjang shift)';
        }

        $unit = $snapshot['rounding_unit_minutes'] ?? 1;
        if ($unit <= 1) {
            return 'Per menit (tanpa pembulatan)';
        }

        $mode = ($snapshot['rounding_mode'] ?? 'down') === 'nearest' ? 'ke terdekat' : 'ke bawah';

        return "Per {$unit} menit, {$mode}";
    }
}
