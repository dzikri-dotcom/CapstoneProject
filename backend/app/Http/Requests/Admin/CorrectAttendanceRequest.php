<?php

namespace App\Http\Requests\Admin;

use App\Enums\AttendanceStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CorrectAttendanceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'status' => ['required', Rule::enum(AttendanceStatus::class)],
            'check_in_time' => ['nullable', 'required_unless:status,absent', 'date_format:H:i'],
            'check_out_time' => ['nullable', 'date_format:H:i', 'after:check_in_time'],
            'correction_note' => ['required', 'string', 'max:500'],
        ];
    }

    public function messages(): array
    {
        return [
            'check_in_time.required_unless' => 'Jam check-in wajib diisi kecuali status tidak hadir.',
            'check_out_time.after' => 'Jam check-out harus setelah jam check-in.',
            'correction_note.required' => 'Catatan alasan koreksi wajib diisi.',
        ];
    }

    public function attributes(): array
    {
        return [
            'status' => 'status presensi',
            'check_in_time' => 'jam check-in',
            'check_out_time' => 'jam check-out',
        ];
    }
}
