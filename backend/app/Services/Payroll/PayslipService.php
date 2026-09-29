<?php

namespace App\Services\Payroll;

use App\Exports\PayrollSheetHelper;
use App\Models\PayrollDetail;
use App\Support\Terbilang;
use App\Support\Time;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Response;

/**
 * Slip gaji PDF untuk satu staff pada satu periode.
 */
class PayslipService
{
    public function download(PayrollDetail $detail): Response
    {
        $detail->loadMissing(['payrollPeriod', 'staffProfile.user', 'items']);
        $period = $detail->payrollPeriod;

        $pdf = Pdf::loadView('pdf.payslip', [
            'period' => $period,
            'detail' => $detail,
            'staff' => $detail->staffProfile,
            'items' => $detail->items,
            'absentCount' => $detail->items->whereNull('check_in_at')->count(),
            'paidDuration' => Time::duration($detail->total_minutes),
            'roundingLabel' => PayrollSheetHelper::roundingLabel($period->settings_snapshot ?? []),
            'terbilang' => Terbilang::rupiah((float) $detail->total_amount),
            'issuedAt' => now(),
        ])
            ->setPaper('a4')
            // Hanya huruf yang dipakai yang disematkan, agar ukuran slip kecil.
            ->setOption('isFontSubsettingEnabled', true);

        return $pdf->download(sprintf(
            'slip-gaji_%s_%s_%s.pdf',
            $detail->staffProfile->nim,
            $period->start_date->toDateString(),
            $period->end_date->toDateString(),
        ));
    }
}
