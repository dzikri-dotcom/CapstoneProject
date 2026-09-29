<?php

namespace App\Http\Requests\Admin;

use Carbon\Carbon;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class ReportRangeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'start_date' => ['required', 'date_format:Y-m-d'],
            'end_date' => ['required', 'date_format:Y-m-d', 'after_or_equal:start_date'],
        ];
    }

    public function messages(): array
    {
        return ['end_date.after_or_equal' => 'Tanggal selesai tidak boleh sebelum tanggal mulai.'];
    }

    public function after(): array
    {
        return [
            function (Validator $validator) {
                if ($validator->errors()->isEmpty()
                    && Carbon::parse($this->input('start_date'))->diffInDays(Carbon::parse($this->input('end_date'))) > 366) {
                    $validator->errors()->add('end_date', 'Rentang laporan maksimal satu tahun.');
                }
            },
        ];
    }
}
