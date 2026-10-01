<?php

use App\Http\Controllers\Api\Admin\AttendanceController as AdminAttendanceController;
use App\Http\Controllers\Api\Admin\ClassScheduleController as AdminClassScheduleController;
use App\Http\Controllers\Api\Admin\HourlyRateController;
use App\Http\Controllers\Api\Admin\OperationalHourController;
use App\Http\Controllers\Api\Admin\PayrollDetailController;
use App\Http\Controllers\Api\Admin\PayrollPeriodController;
use App\Http\Controllers\Api\Admin\PostController;
use App\Http\Controllers\Api\Admin\QrTokenController;
use App\Http\Controllers\Api\Admin\ReportController;
use App\Http\Controllers\Api\Admin\SchedulePeriodController;
use App\Http\Controllers\Api\Admin\SettingController;
use App\Http\Controllers\Api\Admin\ShiftAssignmentController;
use App\Http\Controllers\Api\Admin\ShiftController;
use App\Http\Controllers\Api\Admin\StaffController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\Staff\AttendanceController as StaffAttendanceController;
use App\Http\Controllers\Api\Staff\ClassScheduleController as StaffClassScheduleController;
use App\Http\Controllers\Api\Staff\DashboardController as StaffDashboardController;
use App\Http\Controllers\Api\Staff\PayrollController as StaffPayrollController;
use App\Http\Controllers\Api\Staff\ShiftController as StaffShiftController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
| Semua route di file ini otomatis diberi prefix /api.
*/

// Cek koneksi frontend → backend.
Route::get('/ping', fn () => response()->json([
    'status' => 'ok',
    'app' => config('app.name'),
    'waktu_server' => now()->toIso8601String(),
    'zona_waktu' => config('app.timezone'),
]));

Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:login');

Route::middleware('auth:sanctum')->group(function () {
    Route::get('/me', [AuthController::class, 'me']);
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::put('/me/password', [AuthController::class, 'updatePassword']);

    /*
    | Admin (koordinator logistik)
    */
    Route::middleware('role:admin')->prefix('admin')->group(function () {
        // Data staff
        Route::apiResource('staff', StaffController::class)->parameters(['staff' => 'staffProfile']);
        Route::post('staff/{staffProfile}/reset-password', [StaffController::class, 'resetPassword']);

        // Jadwal kuliah & jam kosong staff
        Route::get('staff/{staffProfile}/class-schedules', [AdminClassScheduleController::class, 'index']);
        Route::post('staff/{staffProfile}/class-schedules', [AdminClassScheduleController::class, 'store']);
        Route::get('staff/{staffProfile}/availability', [AdminClassScheduleController::class, 'availability']);
        Route::put('class-schedules/{classSchedule}', [AdminClassScheduleController::class, 'update']);
        Route::delete('class-schedules/{classSchedule}', [AdminClassScheduleController::class, 'destroy']);

        // Pengaturan sistem
        Route::get('settings', [SettingController::class, 'show']);
        Route::put('settings', [SettingController::class, 'update']);
        Route::get('operational-hours', [OperationalHourController::class, 'index']);
        Route::put('operational-hours', [OperationalHourController::class, 'update']);

        // Shift (jam presensi) & pos jaga
        Route::apiResource('shifts', ShiftController::class)->except('show');
        Route::apiResource('posts', PostController::class)->except('show');

        // Tarif per jam
        Route::get('hourly-rates', [HourlyRateController::class, 'index']);
        Route::get('hourly-rates/history', [HourlyRateController::class, 'history']);
        Route::post('hourly-rates', [HourlyRateController::class, 'store']);
        Route::delete('hourly-rates/{effectiveDate}', [HourlyRateController::class, 'destroy'])
            ->where('effectiveDate', '\d{4}-\d{2}-\d{2}');

        // Jadwal shift (algoritma Round Robin)
        Route::get('schedule-periods', [SchedulePeriodController::class, 'index']);
        Route::post('schedule-periods', [SchedulePeriodController::class, 'store']);
        Route::get('schedule-periods/{schedulePeriod}', [SchedulePeriodController::class, 'show']);
        Route::delete('schedule-periods/{schedulePeriod}', [SchedulePeriodController::class, 'destroy']);
        Route::post('schedule-periods/{schedulePeriod}/generate', [SchedulePeriodController::class, 'generate']);
        Route::post('schedule-periods/{schedulePeriod}/publish', [SchedulePeriodController::class, 'publish']);
        Route::post('schedule-periods/{schedulePeriod}/unpublish', [SchedulePeriodController::class, 'unpublish']);
        Route::get('schedule-periods/{schedulePeriod}/logs', [SchedulePeriodController::class, 'logs']);

        // Edit manual jadwal
        Route::get('shift-slots/{shiftSlot}/candidates', [ShiftAssignmentController::class, 'candidates']);
        Route::post('shift-slots/{shiftSlot}/assignments', [ShiftAssignmentController::class, 'store']);
        Route::post('shift-assignments/swap', [ShiftAssignmentController::class, 'swap']);
        Route::put('shift-assignments/{shiftAssignment}', [ShiftAssignmentController::class, 'update']);
        Route::delete('shift-assignments/{shiftAssignment}', [ShiftAssignmentController::class, 'destroy']);

        // Presensi: layar QR dinamis, monitoring, dan koreksi manual
        Route::post('qr-tokens/current', [QrTokenController::class, 'current']);
        Route::get('attendances', [AdminAttendanceController::class, 'index']);
        Route::put('shift-assignments/{shiftAssignment}/attendance', [AdminAttendanceController::class, 'correct']);

        // Penggajian
        Route::get('payroll-periods', [PayrollPeriodController::class, 'index']);
        Route::post('payroll-periods', [PayrollPeriodController::class, 'store']);
        Route::get('payroll-periods/{payrollPeriod}', [PayrollPeriodController::class, 'show']);
        Route::delete('payroll-periods/{payrollPeriod}', [PayrollPeriodController::class, 'destroy']);
        Route::post('payroll-periods/{payrollPeriod}/calculate', [PayrollPeriodController::class, 'calculate']);
        Route::post('payroll-periods/{payrollPeriod}/finalize', [PayrollPeriodController::class, 'finalize']);
        Route::get('payroll-periods/{payrollPeriod}/export', [PayrollPeriodController::class, 'export']);
        Route::get('payroll-details/{payrollDetail}', [PayrollDetailController::class, 'show']);
        Route::get('payroll-details/{payrollDetail}/slip', [PayrollDetailController::class, 'slip']);

        // Dashboard & laporan
        Route::get('dashboard', [ReportController::class, 'dashboard']);
        Route::get('reports/fairness', [ReportController::class, 'fairness']);
        Route::get('reports/attendance', [ReportController::class, 'attendance']);
        Route::get('reports/attendance/export', [ReportController::class, 'attendanceExport']);
    });

    /*
    | Student staff
    */
    Route::middleware('role:staff')->prefix('staff')->group(function () {
        Route::apiResource('class-schedules', StaffClassScheduleController::class)
            ->except('show')
            ->parameters(['class-schedules' => 'classSchedule']);
        Route::get('availability', [StaffClassScheduleController::class, 'availability']);
        Route::get('shifts', [StaffShiftController::class, 'index']);

        // Presensi dengan QR
        Route::get('attendance/current', [StaffAttendanceController::class, 'current']);
        Route::post('attendance/check-in', [StaffAttendanceController::class, 'checkIn'])->middleware('throttle:20,1');
        Route::post('attendance/check-out', [StaffAttendanceController::class, 'checkOut'])->middleware('throttle:20,1');
        Route::get('attendances', [StaffAttendanceController::class, 'history']);

        // Rekap gaji (hanya periode final)
        Route::get('payrolls', [StaffPayrollController::class, 'index']);
        Route::get('payrolls/{payrollDetail}', [StaffPayrollController::class, 'show']);
        Route::get('payrolls/{payrollDetail}/slip', [StaffPayrollController::class, 'slip']);

        Route::get('dashboard', StaffDashboardController::class);
    });
});
