<?php

namespace App\Http\Requests\Admin;

use App\Services\Attendance\AttendanceService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ListAttendancesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'date' => ['nullable', 'date_format:Y-m-d'],
            'state' => ['nullable', Rule::in(array_keys(AttendanceService::STATE_LABELS))],
        ];
    }
}
