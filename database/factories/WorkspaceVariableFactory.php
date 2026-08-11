<?php

namespace Database\Factories;

use App\Models\Workspace;
use App\Models\WorkspaceVariable;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<WorkspaceVariable>
 */
class WorkspaceVariableFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'workspace_id' => Workspace::factory(),
            'key' => fake()->unique()->word(),
            'value' => fake()->word(),
            'is_secret' => false,
        ];
    }
}
