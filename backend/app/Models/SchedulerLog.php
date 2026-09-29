<?php

namespace App\Models;

use App\Enums\SchedulerLogAction;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'schedule_period_id', 'shift_slot_id', 'step', 'staff_profile_id', 'pointer_position',
    'action', 'reason', 'message', 'meta',
])]
class SchedulerLog extends Model
{
    // Log hanya dicatat sekali, tidak pernah diperbarui.
    public const UPDATED_AT = null;

    protected function casts(): array
    {
        return [
            'action' => SchedulerLogAction::class,
            'meta' => 'array',
        ];
    }

    public function schedulePeriod(): BelongsTo
    {
        return $this->belongsTo(SchedulePeriod::class);
    }

    public function shiftSlot(): BelongsTo
    {
        return $this->belongsTo(ShiftSlot::class);
    }

    public function staffProfile(): BelongsTo
    {
        return $this->belongsTo(StaffProfile::class);
    }
}
