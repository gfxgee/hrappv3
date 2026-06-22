<?php

namespace Database\Factories;

use App\Enum\AttendanceCorrectionType;
use App\Enum\AttendanceStatus;
use App\Models\AttendanceCorrectionRequest;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AttendanceCorrectionRequest>
 */
class AttendanceCorrectionRequestFactory extends Factory
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
            'correction_type' => fake()->randomElement(AttendanceCorrectionType::cases()),
            'corrected_at' => fake()->dateTimeBetween('-1 week', 'now'),
            'reason' => fake()->sentence(),
            'status' => AttendanceStatus::FOR_APPROVAL,
            'remarks' => null,
        ];
    }

    public function status(AttendanceStatus $status): static
    {
        return $this->state(fn (): array => ['status' => $status]);
    }
}
