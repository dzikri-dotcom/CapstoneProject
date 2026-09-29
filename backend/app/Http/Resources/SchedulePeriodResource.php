<?php

namespace App\Http\Resources;

use App\Models\SchedulePeriod;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin SchedulePeriod
 */
class SchedulePeriodResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'start_date' => $this->start_date->toDateString(),
            'end_date' => $this->end_date->toDateString(),
            'status' => $this->status->value,
            'status_label' => $this->status->label(),
            'generated_at' => $this->generated_at?->toIso8601String(),
            'published_at' => $this->published_at?->toIso8601String(),
            'start_pointer' => $this->whenLoaded('startPointerStaff', fn () => $this->pointer($this->startPointerStaff)),
            'end_pointer' => $this->whenLoaded('endPointerStaff', fn () => $this->pointer($this->endPointerStaff)),
            'slots_count' => $this->whenCounted('shiftSlots'),
            'understaffed_slots_count' => $this->whenCounted('understaffed_slots'),
            'assignments_count' => $this->whenCounted('shiftAssignments'),
            'required_staff_total' => $this->when(
                isset($this->required_staff_total),
                fn () => (int) $this->required_staff_total,
            ),
            'slots' => ShiftSlotResource::collection($this->whenLoaded('shiftSlots')),
        ];
    }

    private function pointer($staff): ?array
    {
        return $staff ? ['id' => $staff->id, 'name' => $staff->user?->name] : null;
    }
}
