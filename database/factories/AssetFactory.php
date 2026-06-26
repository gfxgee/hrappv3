<?php

namespace Database\Factories;

use App\Enum\AssetCategory;
use App\Enum\AssetStatus;
use App\Models\Asset;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Asset>
 */
class AssetFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'category' => fake()->randomElement(AssetCategory::cases()),
            'name' => fake()->words(2, true),
            'brand' => fake()->randomElement(['Corsair', 'Dell', 'Logitech', 'Kingston', 'Asus', 'HP']),
            'model' => fake()->bothify('##??-####'),
            'serial_number' => fake()->unique()->bothify('SN-########'),
            'specifications' => fake()->optional()->sentence(),
            'status' => AssetStatus::AVAILABLE,
            'assigned_to' => null,
            'purchased_at' => fake()->optional()->dateTimeBetween('-3 years', 'now'),
            'notes' => null,
        ];
    }

    /**
     * The asset is currently assigned to an employee.
     */
    public function assignedTo(User $user): static
    {
        return $this->state(fn (): array => [
            'status' => AssetStatus::ASSIGNED,
            'assigned_to' => $user->id,
        ]);
    }
}
