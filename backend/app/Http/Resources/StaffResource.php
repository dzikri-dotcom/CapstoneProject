<?php

namespace App\Http\Resources;

use App\Models\StaffProfile;
use App\Services\SettingService;
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
            // Batas khusus staff ini (null = mengikuti batas umum) dan batas yang akhirnya berlaku.
            'max_weekly_hours' => $this->max_weekly_hours,
            'effective_max_weekly_hours' => $this->max_weekly_hours ?? $this->defaultMaxWeeklyHours($request),
            'is_active' => $this->is_active,
            'class_schedules_count' => $this->whenCounted('classSchedules'),
            'shift_assignments_count' => $this->whenCounted('shiftAssignments'),
            'class_schedules' => ClassScheduleResource::collection($this->whenLoaded('classSchedules')),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }

    /** Batas umum dari Pengaturan, dibaca sekali per request (bukan sekali per baris staff). */
    private function defaultMaxWeeklyHours(Request $request): int
    {
        if (! $request->attributes->has('default_max_weekly_hours')) {
            $request->attributes->set('default_max_weekly_hours', app(SettingService::class)->get('max_weekly_hours'));
        }

        return $request->attributes->get('default_max_weekly_hours');
    }
}
