<?php

namespace Database\Factories;

use App\Models\ShippingMethod;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ShippingMethod>
 */
class ShippingMethodFactory extends Factory
{
    public function definition(): array
    {
        return [
            'name'           => fake()->unique()->randomElement(['Standard Shipping', 'Express Delivery', 'Next Day', 'Pickup']),
            'description'    => fake()->sentence(),
            'cost'           => fake()->randomFloat(2, 0, 30),
            'estimated_days' => fake()->numberBetween(1, 14),
            'sort_order'     => fake()->numberBetween(0, 10),
            'is_active'      => true,
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => false,
        ]);
    }

    public function free(): static
    {
        return $this->state(fn (array $attributes) => [
            'cost' => 0,
        ]);
    }
}
