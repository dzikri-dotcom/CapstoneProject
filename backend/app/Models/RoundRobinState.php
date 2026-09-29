<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Table('round_robin_state')]
#[Fillable(['next_staff_profile_id', 'last_schedule_period_id'])]
class RoundRobinState extends Model
{
    /**
     * Tabel ini hanya berisi satu baris; buat otomatis jika belum ada.
     */
    public static function current(): self
    {
        return static::query()->first() ?? static::query()->create();
    }

    public function nextStaff(): BelongsTo
    {
        return $this->belongsTo(StaffProfile::class, 'next_staff_profile_id');
    }

    public function lastSchedulePeriod(): BelongsTo
    {
        return $this->belongsTo(SchedulePeriod::class, 'last_schedule_period_id');
    }
}
