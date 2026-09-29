<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class UpdateOperationalHoursRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'days' => ['required', 'array', 'size:7'],
            'days.*.day_of_week' => ['required', 'integer', 'between:1,7', 'distinct'],
            'days.*.is_active' => ['required', 'boolean'],
            'days.*.open_time' => ['required', 'date_format:H:i'],
            'days.*.close_time' => ['required', 'date_format:H:i', 'after:days.*.open_time'],
        ];
    }

    public function messages(): array
    {
        return [
            'days.*.close_time.after' => 'Jam tutup harus setelah jam buka.',
        ];
    }

    public function attributes(): array
    {
        return [
            'days' => 'jam operasional',
            'days.*.day_of_week' => 'hari',
            'days.*.open_time' => 'jam buka',
            'days.*.close_time' => 'jam tutup',
        ];
    }

    public function after(): array
    {
        return [
            function (Validator $validator) {
                $active = collect($this->input('days', []))->filter(fn ($day) => filter_var($day['is_active'] ?? false, FILTER_VALIDATE_BOOLEAN));
                if ($active->isEmpty()) {
                    $validator->errors()->add('days', 'Minimal satu hari operasional harus aktif.');
                }
            },
        ];
    }
}
