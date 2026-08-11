<?php

namespace Database\Factories;

use App\Models\Request;
use App\Models\RequestFile;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<RequestFile>
 */
class RequestFileFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $filename = fake()->word().'.txt';

        return [
            'request_id' => Request::factory(),
            'filename' => $filename,
            'path' => 'request-files/'.fake()->uuid().'/'.$filename,
            'mime_type' => 'text/plain',
            'size' => fake()->numberBetween(1, 10_000),
        ];
    }
}
