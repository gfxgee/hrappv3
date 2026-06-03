<?php

namespace Database\Factories;

use App\Models\Badge;
use App\Models\Praise;
use App\Models\PraiseSession;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Praise>
 */
class PraiseFactory extends Factory
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
            'recipient_id' => User::factory(),
            'praise_session_id' => null,
            'badge_id' => Badge::factory(),
            'message' => fake()->sentence(),
        ];
    }

    public function inSession(?PraiseSession $session = null): static
    {
        return $this->state(fn (): array => [
            'praise_session_id' => $session?->id ?? PraiseSession::factory(),
        ]);
    }

    public function withoutBadge(): static
    {
        return $this->state(fn (): array => ['badge_id' => null]);
    }
}
