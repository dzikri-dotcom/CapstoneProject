<?php

namespace App\Http\Requests\Admin;

use App\Models\Shift;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class ShiftRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:50'],
            'start_time' => ['required', 'date_format:H:i'],
            'end_time' => ['required', 'date_format:H:i', 'after:start_time'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'end_time.after' => 'Jam selesai harus setelah jam mulai.',
        ];
    }

    public function attributes(): array
    {
        return [
            'name' => 'nama shift',
            'start_time' => 'jam mulai',
            'end_time' => 'jam selesai',
            'is_active' => 'status aktif',
        ];
    }

    /**
     * Shift aktif tidak boleh beririsan jamnya: satu staff hanya bisa menjaga satu shift pada satu waktu,
     * dan presensi mencocokkan check-in ke shift berdasarkan jam.
     */
    public function after(): array
    {
        return [
            function (Validator $validator) {
                if ($validator->errors()->isNotEmpty() || ! $this->boolean('is_active', true)) {
                    return;
                }

                $overlap = Shift::active()
                    ->when($this->route('shift'), fn ($query, Shift $shift) => $query->whereKeyNot($shift->id))
                    ->where('start_time', '<', $this->input('end_time').':00')
                    ->where('end_time', '>', $this->input('start_time').':00')
                    ->first();

                if ($overlap) {
                    $validator->errors()->add('start_time', sprintf(
                        'Jam shift beririsan dengan %s (%s–%s).',
                        $overlap->name,
                        substr($overlap->start_time, 0, 5),
                        substr($overlap->end_time, 0, 5),
                    ));
                }
            },
        ];
    }
}
