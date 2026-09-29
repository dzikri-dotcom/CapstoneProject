<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Pindah slot (shift_slot_id) dan/atau ganti staff (staff_profile_id) pada satu penugasan.
 */
class UpdateAssignmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'shift_slot_id' => ['nullable', 'integer', 'required_without:staff_profile_id', 'exists:shift_slots,id'],
            'staff_profile_id' => ['nullable', 'integer', 'required_without:shift_slot_id', 'exists:staff_profiles,id'],
        ];
    }
}
