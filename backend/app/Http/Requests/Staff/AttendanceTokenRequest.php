<?php

namespace App\Http\Requests\Staff;

use Illuminate\Foundation\Http\FormRequest;

class AttendanceTokenRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'token' => ['required', 'string', 'max:100'],
        ];
    }

    public function messages(): array
    {
        return [
            'token.required' => 'Scan QR Code di ruang logistik terlebih dahulu.',
        ];
    }
}
