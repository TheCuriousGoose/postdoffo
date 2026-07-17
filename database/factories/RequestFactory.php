<?php

namespace Database\Factories;

use App\Enums\BodyType;
use App\Enums\HttpMethod;
use App\Models\Collection;
use App\Models\Request;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Request>
 */
class RequestFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'collection_id' => Collection::factory(),
            'name' => fake()->words(2, true),
            'method' => HttpMethod::Get,
            'url' => fake()->url(),
            'order' => 0,
            'headers' => [],
            'query_params' => [],
            'body' => null,
            'body_type' => BodyType::None,
        ];
    }
}
