<?php

namespace Database\Factories;

use App\Enum\AttendanceStatus;
use App\Models\OverTimeRequest;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<OverTimeRequest>
 */
class OverTimeRequestFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'reason' => fake()->sentence(),
            'request_date' => fake()->dateTimeBetween('-2 weeks', '+1 week'),
            'hours' => fake()->randomElement([1.0, 1.5, 2.0, 2.5, 3.0, 4.0]),
            'status' => AttendanceStatus::FOR_APPROVAL,
            'approved_date' => null,
        ];
    }

    public function approved(): static
    {
        return $this->state(fn (): array => [
            'status' => AttendanceStatus::APPROVED,
            'approved_date' => now(),
        ]);
    }
}
