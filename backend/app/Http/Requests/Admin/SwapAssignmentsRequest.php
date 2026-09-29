<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class SwapAssignmentsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'first_assignment_id' => ['required', 'integer', 'exists:shift_assignments,id'],
            'second_assignment_id' => ['required', 'integer', 'different:first_assignment_id', 'exists:shift_assignments,id'],
        ];
    }

    public function attributes(): array
    {
        return [
            'first_assignment_id' => 'penugasan pertama',
            'second_assignment_id' => 'penugasan kedua',
        ];
    }
}
