<?php

namespace Database\Factories;

use App\Enum\AnnouncementType;
use App\Models\Announcement;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Announcement>
 */
class AnnouncementFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'title' => fake()->sentence(3),
            'message' => '<p>'.fake()->sentence().'</p>',
            'type' => fake()->randomElement(AnnouncementType::cases()),
            'is_active' => true,
            'starts_at' => null,
            'ends_at' => null,
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn (): array => ['is_active' => false]);
    }
}
