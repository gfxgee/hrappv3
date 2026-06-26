<?php

namespace Database\Factories;

use App\Enum\AssignmentType;
use App\Models\Asset;
use App\Models\AssetAssignment;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AssetAssignment>
 */
class AssetAssignmentFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'asset_id' => Asset::factory(),
            'user_id' => User::factory(),
            'type' => AssignmentType::PERMANENT,
            'assigned_at' => now(),
            'due_at' => null,
            'returned_at' => null,
            'assigned_by' => null,
            'received_by' => null,
            'notes' => null,
        ];
    }

    /**
     * A borrow with an expected return date.
     */
    public function borrow(): static
    {
        return $this->state(fn (): array => [
            'type' => AssignmentType::BORROW,
            'due_at' => now()->addWeek(),
        ]);
    }

    /**
     * An assignment that has already been returned.
     */
    public function returned(): static
    {
        return $this->state(fn (): array => [
            'returned_at' => now(),
        ]);
    }
}
