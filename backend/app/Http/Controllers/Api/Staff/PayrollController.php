<?php

namespace App\Http\Controllers\Api\Staff;

use App\Enums\PayrollStatus;
use App\Http\Controllers\Controller;
use App\Http\Resources\PayrollDetailResource;
use App\Models\PayrollDetail;
use App\Services\Payroll\PayslipService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;

/**
 * Rekap gaji milik staff yang login. Hanya periode yang sudah final yang terlihat.
 */
class PayrollController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $details = $this->myFinalDetails($request)
            ->with('payrollPeriod')
            ->withCount(['items as absent_items_count' => fn ($q) => $q->whereNull('check_in_at')])
            ->get()
            ->sortByDesc(fn (PayrollDetail $detail) => $detail->payrollPeriod->start_date)
            ->values();

        return PayrollDetailResource::collection($details);
    }

    public function show(Request $request, int $payrollDetail): PayrollDetailResource
    {
        $detail = $this->myFinalDetails($request)->findOrFail($payrollDetail);

        return new PayrollDetailResource($detail->load(['payrollPeriod', 'items']));
    }

    public function slip(Request $request, int $payrollDetail, PayslipService $payslips): Response
    {
        return $payslips->download($this->myFinalDetails($request)->findOrFail($payrollDetail));
    }

    private function myFinalDetails(Request $request): Builder
    {
        return PayrollDetail::query()
            ->where('staff_profile_id', $request->user()->staffProfile->id)
            ->whereHas('payrollPeriod', fn (Builder $period) => $period->where('status', PayrollStatus::Final));
    }
}
