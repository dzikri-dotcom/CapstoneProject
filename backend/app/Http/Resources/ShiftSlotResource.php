<?php

namespace App\Http\Resources;

use App\Enums\DayOfWeek;
use App\Models\ShiftSlot;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin ShiftSlot
 */
class ShiftSlotResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'date' => $this->date->toDateString(),
            'day_of_week' => $this->date->dayOfWeekIso,
            'day_label' => DayOfWeek::from($this->date->dayOfWeekIso)->label(),
            'shift' => ['id' => $this->shift_id, 'name' => $this->shift_name],
            'post' => ['id' => $this->post_id, 'name' => $this->post_name, 'location' => $this->post_location],
            'start_time' => substr($this->start_time, 0, 5),
            'end_time' => substr($this->end_time, 0, 5),
            'duration_minutes' => $this->durationMinutes(),
            'required_staff' => $this->required_staff,
            'assignments' => ShiftAssignmentResource::collection($this->whenLoaded('assignments')),
            'is_understaffed' => $this->whenLoaded(
                'assignments',
                fn () => $this->assignments->count() < $this->required_staff,
            ),
        ];
    }
}
