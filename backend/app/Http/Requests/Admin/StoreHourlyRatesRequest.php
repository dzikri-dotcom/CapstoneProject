<?php

namespace App\Http\Requests\Admin;

use App\Services\HourlyRateService;
use Carbon\Carbon;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class StoreHourlyRatesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'effective_date' => ['required', 'date_format:Y-m-d'],
            'rates' => ['required', 'array', 'min:1', 'max:24'],
            'rates.*.start_time' => ['required', 'date_format:H:i', 'distinct'],
            'rates.*.end_time' => ['required', 'date_format:H:i', 'after:rates.*.start_time'],
            'rates.*.rate' => ['required', 'integer', 'min:0', 'max:10000000'],
        ];
    }

    public function messages(): array
    {
        return [
            'rates.*.rate.required' => 'Tarif untuk setiap slot jam wajib diisi.',
            'rates.*.end_time.after' => 'Jam selesai tarif harus setelah jam mulai.',
            'rates.*.start_time.distinct' => 'Ada slot jam yang ganda.',
        ];
    }

    public function attributes(): array
    {
        return [
            'rates.*.start_time' => 'jam mulai',
            'rates.*.end_time' => 'jam selesai',
            'rates.*.rate' => 'tarif',
        ];
    }

    /**
     * Tarif tidak boleh berlaku mundur ke periode gaji yang sudah final.
     */
    public function after(): array
    {
        return [
            function (Validator $validator) {
                $lockedUntil = app(HourlyRateService::class)->lockedUntil();
                if ($lockedUntil && $this->input('effective_date') <= $lockedUntil) {
                    $validator->errors()->add('effective_date', sprintf(
                        'Tanggal berlaku harus setelah %s karena periode gaji sampai tanggal tersebut sudah final.',
                        Carbon::parse($lockedUntil)->translatedFormat('j F Y'),
                    ));
                }
            },
        ];
    }
}
