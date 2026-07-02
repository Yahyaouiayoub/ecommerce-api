<?php

namespace Database\Factories;

use App\Models\Cart;
use App\Models\Product;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Cart>
 */
class CartFactory extends Factory
{
    public function definition(): array
    {
        return [
            'user_id'    => User::factory(),
            'product_id' => Product::factory(),
            'quantity'   => fake()->numberBetween(1, 5),
            'status'     => 'active',
        ];
    }

    public function active(): static
    {
        return $this->state(fn (array $attributes) => ['status' => 'active']);
    }

    public function converted(): static
    {
        return $this->state(fn (array $attributes) => ['status' => 'converted']);
    }

    public function abandoned(): static
    {
        return $this->state(fn (array $attributes) => ['status' => 'abandoned']);
    }
}
