<?php

namespace Database\Factories;

use App\Models\Review;
use App\Models\Product;
use App\Models\User;
use App\Models\Order;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Review>
 */
class ReviewFactory extends Factory
{
    public function definition(): array
    {
        return [
            'product_id' => Product::factory(),
            'user_id'    => User::factory(),
            'order_id'   => Order::factory(),
            'rating'     => fake()->numberBetween(1, 5),
            'comment'    => fake()->optional(0.8)->sentence(),
        ];
    }
}
