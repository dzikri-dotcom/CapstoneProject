<?php

namespace App\Http\Resources;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin User
 */
class UserResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'role' => $this->role->value,
            'role_label' => $this->role->label(),
            'staff_profile' => $this->whenLoaded('staffProfile', fn () => $this->staffProfile ? [
                'id' => $this->staffProfile->id,
                'nim' => $this->staffProfile->nim,
                'phone' => $this->staffProfile->phone,
                'study_program' => $this->staffProfile->study_program,
                'is_active' => $this->staffProfile->is_active,
            ] : null),
        ];
    }
}
