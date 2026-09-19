<?php

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<\App\Models\IdempotencyKey>
 */
class IdempotencyKeyFactory extends Factory
{
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'key' => fake()->unique()->uuid(),
            'request_hash' => hash('sha256', fake()->uuid()),
            'response_status' => null,
            'response_body' => null,
            'expires_at' => now()->addHours(24),
        ];
    }
}
