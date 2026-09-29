<?php

namespace Database\Factories;

use App\Models\StaffProfile;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<StaffProfile>
 */
class StaffProfileFactory extends Factory
{
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'nim' => fake()->unique()->numerify('120#######'),
            'phone' => fake()->numerify('08##########'),
            'study_program' => fake()->randomElement([
                'S1 Informatika', 'S1 Sistem Informasi', 'S1 Teknik Logistik', 'S1 Teknik Industri',
            ]),
            'is_active' => true,
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => false,
        ]);
    }
}
