<?php

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<\App\Models\Order>
 */
class OrderFactory extends Factory
{
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'order_number' => 'ORD-' . fake()->unique()->numerify('########'),
            'status' => 'pending',
            'subtotal' => 0,
            'total' => 0,
            'currency' => 'INR',
        ];
    }
}
