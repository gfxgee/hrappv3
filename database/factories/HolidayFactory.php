<?php

namespace Database\Factories;

use App\Models\Holiday;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Holiday>
 */
class HolidayFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->randomElement([
                'New Year\'s Day', 'Labor Day', 'Independence Day',
                'Bonifacio Day', 'Rizal Day', 'Christmas Day',
            ]),
            'date' => fake()->unique()->dateTimeBetween('-1 month', '+6 months'),
        ];
    }
}
