<?php

namespace App\Http\Controllers\Api\Admin;

use App\Exports\AttendanceRecapExport;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ReportRangeRequest;
use App\Services\Reports\DashboardService;
use App\Services\Reports\ReportService;
use Illuminate\Http\JsonResponse;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class ReportController extends Controller
{
    public function __construct(private readonly ReportService $reports)
    {
    }

    public function dashboard(DashboardService $dashboard): JsonResponse
    {
        return response()->json(['data' => $dashboard->admin()]);
    }

    /**
     * Laporan keadilan distribusi jadwal (bahan evaluasi algoritma Round Robin).
     */
    public function fairness(ReportRangeRequest $request): JsonResponse
    {
        return response()->json(['data' => $this->reports->fairness($request->validated('start_date'), $request->validated('end_date'))]);
    }

    public function attendance(ReportRangeRequest $request): JsonResponse
    {
        return response()->json(['data' => $this->reports->attendance($request->validated('start_date'), $request->validated('end_date'))]);
    }

    public function attendanceExport(ReportRangeRequest $request): BinaryFileResponse
    {
        $report = $this->reports->attendance($request->validated('start_date'), $request->validated('end_date'));

        return Excel::download(
            new AttendanceRecapExport($report),
            "rekap-kehadiran_{$report['from']}_{$report['to']}.xlsx",
        );
    }
}
