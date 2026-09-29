<?php

namespace App\Http\Resources;

use App\Models\StaffProfile;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Data student staff (gabungan staff_profiles + users).
 *
 * @mixin StaffProfile
 */
class StaffResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'user_id' => $this->user_id,
            'name' => $this->user->name,
            'email' => $this->user->email,
            'nim' => $this->nim,
            'phone' => $this->phone,
            'study_program' => $this->study_program,
            'is_active' => $this->is_active,
            'class_schedules_count' => $this->whenCounted('classSchedules'),
            'shift_assignments_count' => $this->whenCounted('shiftAssignments'),
            'class_schedules' => ClassScheduleResource::collection($this->whenLoaded('classSchedules')),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
