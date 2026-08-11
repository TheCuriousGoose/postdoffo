<?php

namespace Database\Factories;

use App\Models\RequestCookie;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<RequestCookie>
 */
class RequestCookieFactory extends Factory
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
            'user_id' => User::factory(),
            'domain' => 'api.example.com',
            'path' => '/',
            'name' => 'session',
            'value' => fake()->sha256(),
            'expires_at' => null,
            'secure' => false,
            'http_only' => true,
        ];
    }
}
