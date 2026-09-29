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
            'shift_duration_minutes' => ['required', 'integer', 'between:30,480', 'multiple_of:30'],
            'staff_per_shift' => ['required', 'integer', 'between:1,10'],
            'max_weekly_hours' => ['required', 'integer', 'between:1,60'],
            'late_tolerance_minutes' => ['required', 'integer', 'between:0,120'],
            'checkin_early_minutes' => ['required', 'integer', 'between:0,120'],
            'checkout_grace_minutes' => ['required', 'integer', 'between:0,240'],
            'rounding_unit_minutes' => ['required', 'integer', Rule::in([1, 5, 15, 30, 60])],
            'rounding_mode' => ['required', Rule::in(['down', 'nearest'])],
            'qr_refresh_seconds' => ['required', 'integer', 'between:30,60'],
        ];
    }

    public function attributes(): array
    {
        return [
            'shift_duration_minutes' => 'durasi shift',
            'staff_per_shift' => 'jumlah staff per shift',
            'max_weekly_hours' => 'batas jam kerja per minggu',
            'late_tolerance_minutes' => 'toleransi keterlambatan',
            'checkin_early_minutes' => 'batas check-in lebih awal',
            'checkout_grace_minutes' => 'batas check-out',
            'rounding_unit_minutes' => 'satuan pembulatan',
            'rounding_mode' => 'cara pembulatan',
            'qr_refresh_seconds' => 'interval pergantian QR',
        ];
    }
}
