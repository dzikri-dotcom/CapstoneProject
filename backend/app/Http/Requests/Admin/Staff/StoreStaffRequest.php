<?php

namespace App\Http\Requests\Admin\Staff;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreStaffRequest extends FormRequest
{
    public const PHONE_REGEX = '/^(\+62|62|0)8[0-9]{7,12}$/';

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
        return [
            'name' => ['required', 'string', 'max:100'],
            'email' => ['required', 'email', 'max:100', Rule::unique('users', 'email')],
            'nim' => ['required', 'string', 'alpha_num', 'max:20', Rule::unique('staff_profiles', 'nim')],
            'phone' => ['nullable', 'string', 'max:20', 'regex:'.self::PHONE_REGEX],
            'study_program' => ['required', 'string', 'max:100'],
            'password' => ['required', 'string', 'min:8'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }
}
