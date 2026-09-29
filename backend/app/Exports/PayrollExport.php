<?php

namespace App\Exports;

use App\Models\PayrollPeriod;
use Maatwebsite\Excel\Concerns\Export;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;

/**
 * Rekap gaji satu periode dalam file Excel dengan dua sheet: Rekap (per staff) dan Rincian (per shift).
 */
class PayrollExport implements Export, WithMultipleSheets
{
    public function __construct(private readonly PayrollPeriod $period)
    {
    }

    public function sheets(): array
    {
        $this->period->loadMissing(['details.staffProfile.user', 'details.items']);

        return [
            new PayrollSummarySheet($this->period),
            new PayrollItemsSheet($this->period),
        ];
    }
}
