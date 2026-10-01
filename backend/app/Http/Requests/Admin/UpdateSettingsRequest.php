<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateSettingsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'max_weekly_hours' => ['required', 'integer', 'between:1,60'],
            'late_tolerance_minutes' => ['required', 'integer', 'between:0,120'],
            'max_late_minutes' => ['required', 'integer', 'between:1,480', 'gte:late_tolerance_minutes'],
            'checkin_early_minutes' => ['required', 'integer', 'between:0,120'],
            'checkout_grace_minutes' => ['required', 'integer', 'between:0,240'],
            'salary_deduction_enabled' => ['required', 'boolean'],
            'rounding_unit_minutes' => ['required', 'integer', Rule::in([1, 5, 15, 30, 60])],
            'rounding_mode' => ['required', Rule::in(['down', 'nearest'])],
            'qr_refresh_seconds' => ['required', 'integer', 'between:30,60'],
        ];
    }

    public function messages(): array
    {
        return [
            'max_late_minutes.gte' => 'Maksimal keterlambatan tidak boleh lebih kecil dari toleransi keterlambatan.',
        ];
    }

    public function attributes(): array
    {
        return [
            'max_weekly_hours' => 'batas jam kerja per minggu',
            'late_tolerance_minutes' => 'toleransi keterlambatan',
            'max_late_minutes' => 'maksimal keterlambatan',
            'checkin_early_minutes' => 'batas check-in lebih awal',
            'checkout_grace_minutes' => 'batas check-out',
            'salary_deduction_enabled' => 'potongan gaji',
            'rounding_unit_minutes' => 'satuan pembulatan',
            'rounding_mode' => 'cara pembulatan',
            'qr_refresh_seconds' => 'interval pergantian QR',
        ];
    }
}
