<?php

namespace Database\Factories;

use App\Models\Environment;
use App\Models\EnvironmentVariable;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<EnvironmentVariable>
 */
class EnvironmentVariableFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'environment_id' => Environment::factory(),
            'key' => fake()->unique()->word(),
            'value' => fake()->word(),
            'is_secret' => false,
        ];
    }
}
