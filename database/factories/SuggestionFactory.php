<?php

namespace Database\Factories;

use App\Enum\SuggestionStatus;
use App\Models\Suggestion;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Suggestion>
 */
class SuggestionFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'category' => fake()->randomElement(Suggestion::CATEGORIES),
            'message' => fake()->paragraph(),
            'status' => SuggestionStatus::NEW,
        ];
    }

    public function status(SuggestionStatus $status): static
    {
        return $this->state(fn (): array => ['status' => $status]);
    }
}
