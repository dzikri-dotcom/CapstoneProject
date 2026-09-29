<?php

namespace App\Http\Requests\Admin;

use App\Models\SchedulePeriod;
use Carbon\Carbon;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class StoreSchedulePeriodRequest extends FormRequest
{
    public const MAX_DAYS = 31;

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
        return [
            'end_date.after_or_equal' => 'Tanggal selesai tidak boleh sebelum tanggal mulai.',
        ];
    }

    public function after(): array
    {
        return [
            function (Validator $validator) {
                if ($validator->errors()->isNotEmpty()) {
                    return;
                }

                $start = Carbon::parse($this->input('start_date'));
                $end = Carbon::parse($this->input('end_date'));

                if ($start->diffInDays($end) + 1 > self::MAX_DAYS) {
                    $validator->errors()->add('end_date', 'Satu periode jadwal maksimal '.self::MAX_DAYS.' hari.');

                    return;
                }

                $overlap = SchedulePeriod::query()
                    ->where('start_date', '<=', $end->toDateString())
                    ->where('end_date', '>=', $start->toDateString())
                    ->first();

                if ($overlap) {
                    $validator->errors()->add('start_date', sprintf(
                        'Tanggal bertumpuk dengan periode %s – %s yang sudah ada.',
                        $overlap->start_date->translatedFormat('j M Y'),
                        $overlap->end_date->translatedFormat('j M Y'),
                    ));
                }
            },
        ];
    }
}
