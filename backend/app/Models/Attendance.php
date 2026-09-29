<?php

namespace App\Models;

use App\Enums\AttendanceMethod;
use App\Enums\AttendanceStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'shift_assignment_id', 'staff_profile_id', 'check_in_at', 'check_out_at',
    'check_in_method', 'check_out_method', 'status',
    'is_corrected', 'correction_note', 'corrected_by', 'corrected_at',
])]
class Attendance extends Model
{
    protected function casts(): array
    {
        return [
            'check_in_at' => 'datetime',
            'check_out_at' => 'datetime',
            'check_in_method' => AttendanceMethod::class,
            'check_out_method' => AttendanceMethod::class,
            'status' => AttendanceStatus::class,
            'is_corrected' => 'boolean',
            'corrected_at' => 'datetime',
        ];
    }

    public function shiftAssignment(): BelongsTo
    {
        return $this->belongsTo(ShiftAssignment::class);
    }

    public function staffProfile(): BelongsTo
    {
        return $this->belongsTo(StaffProfile::class);
    }

    public function corrector(): BelongsTo
    {
        return $this->belongsTo(User::class, 'corrected_by');
    }
}
