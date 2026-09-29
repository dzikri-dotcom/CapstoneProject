<?php

namespace App\Http\Requests;

use App\Enums\DayOfWeek;
use App\Models\ClassSchedule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

/**
 * Validasi jadwal kuliah, dipakai oleh staff (jadwal sendiri) maupun admin (jadwal staff mana pun).
 */
class ClassScheduleRequest extends FormRequest
{
    /**
     * Staff hanya boleh mengubah jadwal kuliahnya sendiri.
     */
    public function authorize(): bool
    {
        $user = $this->user();
        if ($user->isAdmin()) {
            return true;
        }

        /** @var ClassSchedule|null $schedule */
        $schedule = $this->route('classSchedule');

        return ! $schedule || $schedule->staff_profile_id === $user->staffProfile?->id;
    }

    /**
     * Pemilik jadwal: dari jadwal yang diedit, dari URL admin, atau staff yang login.
     */
    public function staffProfileId(): int
    {
        return $this->route('classSchedule')?->staff_profile_id
            ?? $this->route('staffProfile')?->id
            ?? $this->user()->staffProfile->id;
    }

    /**
     * Nama mata kuliah selalu ikut dikirim (null jika dikosongkan), agar saat edit
     * nama lama benar-benar terhapus bila pengguna mengosongkannya.
     */
    protected function prepareForValidation(): void
    {
        $this->merge([
            'course_name' => $this->filled('course_name') ? trim((string) $this->input('course_name')) : null,
        ]);
    }

    public function rules(): array
    {
        return [
            'day_of_week' => ['required', 'integer', 'between:1,7'],
            'start_time' => ['required', 'date_format:H:i'],
            'end_time' => ['required', 'date_format:H:i', 'after:start_time'],
            'course_name' => ['nullable', 'string', 'max:150'],
        ];
    }

    public function messages(): array
    {
        return [
            'end_time.after' => 'Jam selesai harus setelah jam mulai.',
        ];
    }

    /**
     * Tolak jadwal yang beririsan dengan jadwal kuliah lain milik staff yang sama.
     */
    public function after(): array
    {
        return [
            function (Validator $validator) {
                if ($validator->errors()->isNotEmpty()) {
                    return;
                }

                $clash = ClassSchedule::query()
                    ->where('staff_profile_id', $this->staffProfileId())
                    ->where('day_of_week', $this->integer('day_of_week'))
                    ->where('start_time', '<', $this->input('end_time'))
                    ->where('end_time', '>', $this->input('start_time'))
                    ->when($this->route('classSchedule'), fn ($query, ClassSchedule $current) => $query->whereKeyNot($current->id))
                    ->first();

                if ($clash) {
                    $validator->errors()->add('start_time', sprintf(
                        'Bentrok dengan jadwal kuliah lain: %s %s–%s%s.',
                        DayOfWeek::from($clash->day_of_week)->label(),
                        substr($clash->start_time, 0, 5),
                        substr($clash->end_time, 0, 5),
                        $clash->course_name ? " ({$clash->course_name})" : '',
                    ));
                }
            },
        ];
    }
}
