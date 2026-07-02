<?php

namespace Database\Factories;

use App\Models\RefundItem;
use App\Models\Refund;
use App\Models\OrderItem;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<RefundItem>
 */
class RefundItemFactory extends Factory
{
    public function definition(): array
    {
        return [
            'refund_id'    => Refund::factory(),
            'order_item_id' => OrderItem::factory(),
            'quantity'     => fake()->numberBetween(1, 3),
            'amount'       => fake()->randomFloat(2, 10, 100),
        ];
    }
}
