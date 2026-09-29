<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['schedule_period_id', 'date', 'start_time', 'end_time', 'required_staff'])]
class ShiftSlot extends Model
{
    protected function casts(): array
    {
        return [
            'date' => 'date',
            'required_staff' => 'integer',
        ];
    }

    public function schedulePeriod(): BelongsTo
    {
        return $this->belongsTo(SchedulePeriod::class);
    }

    public function assignments(): HasMany
    {
        return $this->hasMany(ShiftAssignment::class);
    }

    /**
     * Staff yang ditugaskan di slot ini (melalui tabel shift_assignments).
     */
    public function staff(): BelongsToMany
    {
        return $this->belongsToMany(StaffProfile::class, 'shift_assignments')
            ->withPivot(['id', 'source'])
            ->withTimestamps();
    }

    public function durationMinutes(): int
    {
        return (int) Carbon::parse($this->start_time)->diffInMinutes(Carbon::parse($this->end_time));
    }
}
