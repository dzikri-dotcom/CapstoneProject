<?php

namespace App\Http\Resources;

use App\Enums\DayOfWeek;
use App\Models\ShiftAssignment;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Shift milik staff yang login, beserta rekan satu pos.
 *
 * @mixin ShiftAssignment
 */
class StaffShiftResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $slot = $this->shiftSlot;

        return [
            'id' => $this->id,
            'date' => $slot->date->toDateString(),
            'day_label' => DayOfWeek::from($slot->date->dayOfWeekIso)->label(),
            'shift_name' => $slot->shift_name,
            'post_name' => $slot->post_name,
            'post_location' => $slot->post_location,
            'start_time' => substr($slot->start_time, 0, 5),
            'end_time' => substr($slot->end_time, 0, 5),
            'duration_minutes' => $slot->durationMinutes(),
            'teammates' => $slot->assignments
                ->where('staff_profile_id', '!=', $this->staff_profile_id)
                ->map(fn (ShiftAssignment $other) => $other->staffProfile->user->name)
                ->values(),
        ];
    }
}
