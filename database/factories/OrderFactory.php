<?php

namespace Database\Factories;

use App\Models\Order;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Order>
 */
class OrderFactory extends Factory
{
    public function definition(): array
    {
        return [
            'user_id'        => User::factory(),
            'order_number'   => 'ORD-' . fake()->unique()->numerify('########'),
            'total_price'    => fake()->randomFloat(2, 50, 500),
            'status'         => fake()->randomElement(['pending', 'processing', 'shipped', 'delivered']),
            'payment_method' => fake()->randomElement(['cod', 'card']),
        ];
    }

    public function pending(): static
    {
        return $this->state(fn (array $attributes) => ['status' => 'pending']);
    }

    public function processing(): static
    {
        return $this->state(fn (array $attributes) => ['status' => 'processing']);
    }

    public function shipped(): static
    {
        return $this->state(fn (array $attributes) => ['status' => 'shipped']);
    }

    public function delivered(): static
    {
        return $this->state(fn (array $attributes) => ['status' => 'delivered']);
    }

    public function cancelled(): static
    {
        return $this->state(fn (array $attributes) => ['status' => 'cancelled']);
    }
}
