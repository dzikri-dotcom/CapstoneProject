<?php

namespace App\Http\Resources;

use App\Enums\DayOfWeek;
use App\Models\OperationalHour;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin OperationalHour
 */
class OperationalHourResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'day_of_week' => $this->day_of_week,
            'day_label' => DayOfWeek::from($this->day_of_week)->label(),
            'is_active' => $this->is_active,
            'open_time' => substr($this->open_time, 0, 5),
            'close_time' => substr($this->close_time, 0, 5),
        ];
    }
}
