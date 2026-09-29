<?php

namespace App\Models;

use App\Enums\SchedulePeriodStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;

#[Fillable([
    'start_date', 'end_date', 'status', 'start_pointer_staff_id', 'end_pointer_staff_id',
    'generated_at', 'published_at', 'created_by',
])]
class SchedulePeriod extends Model
{
    protected function casts(): array
    {
        return [
            'start_date' => 'date',
            'end_date' => 'date',
            'status' => SchedulePeriodStatus::class,
            'generated_at' => 'datetime',
            'published_at' => 'datetime',
        ];
    }

    public function shiftSlots(): HasMany
    {
        return $this->hasMany(ShiftSlot::class)->orderBy('date')->orderBy('start_time');
    }

    public function shiftAssignments(): HasManyThrough
    {
        return $this->hasManyThrough(ShiftAssignment::class, ShiftSlot::class);
    }

    public function schedulerLogs(): HasMany
    {
        return $this->hasMany(SchedulerLog::class)->orderBy('step');
    }

    public function startPointerStaff(): BelongsTo
    {
        return $this->belongsTo(StaffProfile::class, 'start_pointer_staff_id');
    }

    public function endPointerStaff(): BelongsTo
    {
        return $this->belongsTo(StaffProfile::class, 'end_pointer_staff_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function isDraft(): bool
    {
        return $this->status === SchedulePeriodStatus::Draft;
    }
}
