<?php

namespace App\Http\Requests\Admin\Staff;

use App\Models\StaffProfile;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateStaffRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'email' => mb_strtolower(trim((string) $this->input('email'))),
            'phone' => $this->filled('phone') ? preg_replace('/[\s-]/', '', $this->input('phone')) : null,
        ]);
    }

    public function rules(): array
    {
        /** @var StaffProfile $staff */
        $staff = $this->route('staffProfile');

        return [
            'name' => ['required', 'string', 'max:100'],
            'email' => ['required', 'email', 'max:100', Rule::unique('users', 'email')->ignore($staff->user_id)],
            'nim' => ['required', 'string', 'alpha_num', 'max:20', Rule::unique('staff_profiles', 'nim')->ignore($staff->id)],
            'phone' => ['nullable', 'string', 'max:20', 'regex:'.StoreStaffRequest::PHONE_REGEX],
            'study_program' => ['required', 'string', 'max:100'],
            'max_weekly_hours' => ['nullable', 'integer', 'between:1,60'],
            'is_active' => ['required', 'boolean'],
        ];
    }

    public function attributes(): array
    {
        return [
            'max_weekly_hours' => 'batas jam per minggu',
        ];
    }
}
