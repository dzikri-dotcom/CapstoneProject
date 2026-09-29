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
        $unit = $snapshot['rounding_unit_minutes'] ?? 1;
        if ($unit <= 1) {
            return 'Per menit (tanpa pembulatan)';
        }

        $mode = ($snapshot['rounding_mode'] ?? 'down') === 'nearest' ? 'ke terdekat' : 'ke bawah';

        return "Per {$unit} menit, {$mode}";
    }
}
