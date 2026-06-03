<?php

namespace Database\Factories;

use App\Enum\AttendanceStatus;
use App\Enum\LeaveType;
use App\Models\LeaveRequest;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<LeaveRequest>
 */
class LeaveRequestFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $startDate = fake()->dateTimeBetween('-1 month', '+1 month');
        $endDate = (clone $startDate)->modify('+'.fake()->numberBetween(0, 4).' days');

        return [
            'user_id' => User::factory(),
            'request_type' => fake()->randomElement(LeaveType::all()),
            'start_date' => $startDate,
            'end_date' => $endDate,
            'start_time' => null,
            'end_time' => null,
            'reason' => fake()->sentence(),
            'status' => AttendanceStatus::FOR_APPROVAL,
        ];
    }

    public function approved(): static
    {
        return $this->state(fn (): array => ['status' => AttendanceStatus::APPROVED]);
    }
}
