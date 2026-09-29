<?php

namespace App\Http\Resources;

use App\Models\ShiftAssignment;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin ShiftAssignment
 */
class ShiftAssignmentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'shift_slot_id' => $this->shift_slot_id,
            'staff_profile_id' => $this->staff_profile_id,
            'name' => $this->whenLoaded('staffProfile', fn () => $this->staffProfile->user?->name),
            'source' => $this->source->value,
            'source_label' => $this->source->label(),
            'has_attendance' => $this->when(isset($this->attendance_exists), fn () => (bool) $this->attendance_exists),
        ];
    }
}
