<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['schedule_period_id', 'shift_id', 'post_id', 'shift_name', 'post_name', 'post_location', 'date', 'start_time', 'end_time', 'required_staff'])]
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

    /** Label ringkas slot, misal "08:00–12:00 · Pos 1". */
    public function label(): string
    {
        return substr($this->start_time, 0, 5).'–'.substr($this->end_time, 0, 5).($this->post_name ? " · {$this->post_name}" : '');
    }

    public function durationMinutes(): int
    {
        return (int) Carbon::parse($this->start_time)->diffInMinutes(Carbon::parse($this->end_time));
    }
}
