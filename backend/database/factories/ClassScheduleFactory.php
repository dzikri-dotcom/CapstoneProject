<?php

namespace Database\Factories;

use App\Models\ClassSchedule;
use App\Models\StaffProfile;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ClassSchedule>
 */
class ClassScheduleFactory extends Factory
{
    public function definition(): array
    {
        return [
            'staff_profile_id' => StaffProfile::factory(),
            'day_of_week' => fake()->numberBetween(1, 5),
            'start_time' => '08:00:00',
            'end_time' => '10:30:00',
            'course_name' => fake()->randomElement(['Kalkulus', 'Basis Data', 'Statistika', 'Bahasa Inggris']),
        ];
    }
}
