<?php

namespace Database\Factories;

use App\Models\Badge;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Badge>
 */
class BadgeFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'label' => fake()->unique()->randomElement(['Team Player', 'Innovator', 'Above and Beyond', 'Mentor', 'Customer Hero', 'Bug Squasher']),
            'icon' => fake()->randomElement(['🏆', '⭐', '🚀', '🤝', '💡']),
            'color' => fake()->randomElement(['primary', 'success', 'warning', 'danger']),
            'points' => fake()->randomElement([5, 10, 15, 20]),
            'is_active' => true,
        ];
    }
}
