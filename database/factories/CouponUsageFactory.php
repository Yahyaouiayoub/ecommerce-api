<?php

namespace Database\Factories;

use App\Models\CouponUsage;
use App\Models\Coupon;
use App\Models\Order;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CouponUsage>
 */
class CouponUsageFactory extends Factory
{
    public function definition(): array
    {
        return [
            'coupon_id'       => Coupon::factory(),
            'order_id'        => Order::factory(),
            'user_id'         => User::factory(),
            'discount_amount' => fake()->randomFloat(2, 5, 50),
        ];
    }
}
