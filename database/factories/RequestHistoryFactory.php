<?php

namespace Database\Factories;

use App\Models\RequestHistory;
use App\Models\Workspace;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<RequestHistory>
 */
class RequestHistoryFactory extends Factory
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
            'method' => 'GET',
            'url' => fake()->url(),
            'status_code' => 200,
            'duration_ms' => fake()->numberBetween(10, 500),
            'response_snapshot' => null,
            'executed_at' => now(),
        ];
    }
}
