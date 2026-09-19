<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<\App\Models\Product>
 */
class ProductFactory extends Factory
{
    public function definition(): array
    {
        return [
            'name' => fake()->words(3, true),
            'sku' => fake()->unique()->bothify('SKU-####-????'),
            'price' => fake()->randomFloat(2, 100, 100000),
            'stock_quantity' => fake()->numberBetween(0, 1000),
            'is_active' => true,
        ];
    }
}
