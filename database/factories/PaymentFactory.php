<?php

namespace Database\Factories;

use App\Models\Order;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<\App\Models\Payment>
 */
class PaymentFactory extends Factory
{
    public function definition(): array
    {
        return [
            'order_id' => Order::factory(),
            'provider' => 'mock',
            'transaction_id' => null,
            'amount' => 0,
            'status' => 'pending',
            'paid_at' => null,
        ];
    }
}
