<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\PayrollDetailResource;
use App\Models\PayrollDetail;
use App\Services\Payroll\PayslipService;
use Illuminate\Http\Response;

/**
 * Rincian gaji per tanggal dan slip PDF satu staff (akses admin).
 */
class PayrollDetailController extends Controller
{
    public function show(PayrollDetail $payrollDetail): PayrollDetailResource
    {
        return new PayrollDetailResource($payrollDetail->load(['staffProfile.user:id,name', 'payrollPeriod', 'items']));
    }

    public function slip(PayrollDetail $payrollDetail, PayslipService $payslips): Response
    {
        return $payslips->download($payrollDetail);
    }
}
