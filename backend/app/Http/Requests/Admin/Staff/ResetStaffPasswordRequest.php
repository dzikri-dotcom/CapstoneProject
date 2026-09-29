<?php

namespace App\Http\Requests\Admin\Staff;

use Illuminate\Foundation\Http\FormRequest;

class ResetStaffPasswordRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'password' => ['required', 'string', 'min:8'],
        ];
    }

    public function attributes(): array
    {
        return [
            'password' => 'password baru',
        ];
    }
}
