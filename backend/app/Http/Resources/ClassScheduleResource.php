<?php

namespace App\Http\Resources;

use App\Enums\DayOfWeek;
use App\Models\ClassSchedule;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin ClassSchedule
 */
class ClassScheduleResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'day_of_week' => $this->day_of_week,
            'day_label' => DayOfWeek::from($this->day_of_week)->label(),
            'start_time' => substr($this->start_time, 0, 5),
            'end_time' => substr($this->end_time, 0, 5),
            'course_name' => $this->course_name,
        ];
    }
}
