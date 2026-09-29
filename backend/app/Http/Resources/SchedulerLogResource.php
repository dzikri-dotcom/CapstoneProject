<?php

namespace App\Http\Resources;

use App\Enums\DayOfWeek;
use App\Enums\SkipReason;
use App\Models\SchedulerLog;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin SchedulerLog
 */
class SchedulerLogResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $reason = $this->reason ? SkipReason::tryFrom($this->reason) : null;
        $slot = $this->shiftSlot;

        return [
            'id' => $this->id,
            'step' => $this->step,
            'action' => $this->action->value,
            'action_label' => $this->action->label(),
            'reason' => $this->reason,
            'reason_label' => $reason?->label(),
            'pointer_position' => $this->pointer_position,
            'message' => $this->message,
            'staff' => $this->staff_profile_id ? [
                'id' => $this->staff_profile_id,
                // Nama disimpan di meta saat generate, sehingga log tetap terbaca walau data staff berubah.
                'name' => $this->meta['staff_name'] ?? $this->staffProfile?->user?->name,
            ] : null,
            'slot' => $slot ? [
                'id' => $slot->id,
                'date' => $slot->date->toDateString(),
                'day_label' => DayOfWeek::from($slot->date->dayOfWeekIso)->label(),
                'start_time' => substr($slot->start_time, 0, 5),
                'end_time' => substr($slot->end_time, 0, 5),
                'required_staff' => $slot->required_staff,
            ] : null,
            'meta' => $this->meta,
        ];
    }
}
