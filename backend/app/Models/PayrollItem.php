<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'payroll_detail_id', 'attendance_id', 'date', 'shift_start', 'shift_end',
    'check_in_at', 'check_out_at', 'actual_minutes', 'paid_minutes', 'amount', 'breakdown',
])]
class PayrollItem extends Model
{
    protected function casts(): array
    {
        return [
            'date' => 'date',
            'check_in_at' => 'datetime',
            'check_out_at' => 'datetime',
            'actual_minutes' => 'integer',
            'paid_minutes' => 'integer',
            'amount' => 'decimal:2',
            'breakdown' => 'array',
        ];
    }

    public function payrollDetail(): BelongsTo
    {
        return $this->belongsTo(PayrollDetail::class);
    }

    public function attendance(): BelongsTo
    {
        return $this->belongsTo(Attendance::class);
    }
}
