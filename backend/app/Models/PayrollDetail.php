<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['payroll_period_id', 'staff_profile_id', 'total_shifts', 'total_minutes', 'total_amount'])]
class PayrollDetail extends Model
{
    protected function casts(): array
    {
        return [
            'total_shifts' => 'integer',
            'total_minutes' => 'integer',
            'total_amount' => 'decimal:2',
        ];
    }

    public function payrollPeriod(): BelongsTo
    {
        return $this->belongsTo(PayrollPeriod::class);
    }

    public function staffProfile(): BelongsTo
    {
        return $this->belongsTo(StaffProfile::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(PayrollItem::class)->orderBy('date')->orderBy('shift_start');
    }
}
