<?php

namespace Database\Factories;

use App\Models\PraiseSession;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PraiseSession>
 */
class PraiseSessionFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->monthName().' '.fake()->year().' Awards',
            'is_active' => false,
        ];
    }
}
