<?php

namespace App\Http\Resources;

use App\Models\ShiftAssignment;
use App\Services\Attendance\AttendanceService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Satu penugasan shift beserta presensinya dan keadaannya saat ini
 * (belum dimulai / belum check-in / sedang bertugas / selesai / tidak hadir, dst).
 *
 * @mixin ShiftAssignment
 */
class ShiftAttendanceResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return app(AttendanceService::class)->describe($this->resource);
    }
}
