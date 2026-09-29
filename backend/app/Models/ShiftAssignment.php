<?php

namespace App\Models;

use App\Enums\AssignmentSource;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

#[Fillable(['shift_slot_id', 'staff_profile_id', 'source'])]
class ShiftAssignment extends Model
{
    protected function casts(): array
    {
        return [
            'source' => AssignmentSource::class,
        ];
    }

    public function shiftSlot(): BelongsTo
    {
        return $this->belongsTo(ShiftSlot::class);
    }

    public function staffProfile(): BelongsTo
    {
        return $this->belongsTo(StaffProfile::class);
    }

    public function attendance(): HasOne
    {
        return $this->hasOne(Attendance::class);
    }
}
