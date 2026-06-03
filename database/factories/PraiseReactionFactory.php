<?php

namespace Database\Factories;

use App\Models\Praise;
use App\Models\PraiseReaction;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PraiseReaction>
 */
class PraiseReactionFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'praise_id' => Praise::factory(),
            'user_id' => User::factory(),
            'type' => '❤️',
        ];
    }
}
