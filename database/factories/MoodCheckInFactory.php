<?php

namespace Database\Factories;

use App\Enum\Mood;
use App\Models\MoodCheckIn;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<MoodCheckIn>
 */
class MoodCheckInFactory extends Factory
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
            'mood' => fake()->randomElement(Mood::cases()),
            'note' => null,
            'logged_on' => today(),
        ];
    }

    public function mood(Mood $mood): static
    {
        return $this->state(fn (): array => ['mood' => $mood]);
    }
}
