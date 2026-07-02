<?php

namespace Database\Factories;

use App\Models\Payment;
use App\Models\Order;
use App\Models\Invoice;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Payment>
 */
class PaymentFactory extends Factory
{
    public function definition(): array
    {
        $amount = fake()->randomFloat(2, 20, 500);
        return [
            'order_id'       => Order::factory(),
            'invoice_id'     => Invoice::factory(),
            'amount'         => $amount,
            'currency'       => 'MAD',
            'payment_method' => fake()->randomElement(['cod', 'card']),
            'payment_type'   => 'full',
            'status'         => 'paid',
            'paid_at'        => now(),
        ];
    }

    public function pending(): static
    {
        return $this->state(fn (array $attributes) => [
            'status'  => 'pending',
            'paid_at' => null,
        ]);
    }

    public function failed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status'  => 'failed',
            'paid_at' => null,
        ]);
    }
}
