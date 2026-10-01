<?php

namespace App\Http\Resources;

use App\Models\Shift;
use App\Support\Time;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Shift
 */
class ShiftResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'start_time' => substr($this->start_time, 0, 5),
            'end_time' => substr($this->end_time, 0, 5),
            'duration_minutes' => Time::toMinutes($this->end_time) - Time::toMinutes($this->start_time),
            'is_active' => $this->is_active,
        ];
    }
}
