<?php

namespace App\Http\Controllers\Api\Admin;

use App\Enums\PayrollStatus;
use App\Exports\PayrollExport;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StorePayrollPeriodRequest;
use App\Http\Resources\PayrollPeriodResource;
use App\Models\PayrollDetail;
use App\Models\PayrollPeriod;
use App\Services\Payroll\PayrollService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class PayrollPeriodController extends Controller
{
    public function __construct(private readonly PayrollService $payroll)
    {
    }

    public function index(): AnonymousResourceCollection
    {
        return PayrollPeriodResource::collection(
            PayrollPeriod::query()->withCount('details')->with('finalizer:id,name')->orderByDesc('start_date')->paginate(10)
        );
    }

    public function store(StorePayrollPeriodRequest $request): JsonResponse
    {
        $period = PayrollPeriod::query()->create([
            ...$request->validated(),
            'status' => PayrollStatus::Draft,
            'created_by' => $request->user()->id,
        ]);

        return (new PayrollPeriodResource($period))
            ->additional(['message' => 'Periode penggajian berhasil dibuat.'])
            ->response()
            ->setStatusCode(201);
    }

    /**
     * Rekap per staff (rincian per tanggal diambil lewat PayrollDetailController).
     */
    public function show(PayrollPeriod $payrollPeriod): PayrollPeriodResource
    {
        $payrollPeriod->load([
            'finalizer:id,name',
            'details' => fn ($query) => $query
                ->with('staffProfile.user:id,name')
                ->withCount(['items as absent_items_count' => fn ($q) => $q->whereNull('check_in_at')]),
        ]);
        $payrollPeriod->setRelation(
            'details',
            $payrollPeriod->details->sortBy(fn (PayrollDetail $d) => $d->staffProfile->user->name)->values(),
        );

        return new PayrollPeriodResource($payrollPeriod);
    }

    public function destroy(PayrollPeriod $payrollPeriod): JsonResponse
    {
        $this->payroll->delete($payrollPeriod);

        return response()->json(['message' => 'Periode penggajian berhasil dihapus.']);
    }

    public function calculate(PayrollPeriod $payrollPeriod): JsonResponse
    {
        $period = $this->payroll->calculate($payrollPeriod);

        return response()->json([
            'message' => sprintf(
                'Gaji dihitung untuk %d staff dengan total Rp%s.',
                $period->details()->count(),
                number_format((float) $period->total_amount, 0, ',', '.'),
            ),
        ]);
    }

    public function finalize(Request $request, PayrollPeriod $payrollPeriod): JsonResponse
    {
        $this->payroll->finalize($payrollPeriod, $request->user());

        return response()->json([
            'message' => 'Penggajian difinalkan. Presensi dan tarif pada periode ini kini terkunci.',
        ]);
    }

    public function export(PayrollPeriod $payrollPeriod): BinaryFileResponse
    {
        return Excel::download(new PayrollExport($payrollPeriod), sprintf(
            'rekap-gaji_%s_%s.xlsx',
            $payrollPeriod->start_date->toDateString(),
            $payrollPeriod->end_date->toDateString(),
        ));
    }
}
