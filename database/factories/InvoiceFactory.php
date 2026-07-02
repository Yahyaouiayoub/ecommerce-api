<?php

namespace Database\Factories;

use App\Models\Invoice;
use App\Models\Order;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Invoice>
 */
class InvoiceFactory extends Factory
{
    public function definition(): array
    {
        return [
            'order_id'       => Order::factory(),
            'invoice_number' => 'INV-' . fake()->unique()->numerify('########'),
            'total_amount'   => fake()->randomFloat(2, 50, 500),
            'paid_amount'    => 0,
            'status'         => 'unpaid',
            'billing_name'   => fake()->name(),
            'billing_email'  => fake()->safeEmail(),
            'payment_method' => fake()->randomElement(['cod', 'card']),
            'issued_at'      => now(),
        ];
    }

    public function paid(): static
    {
        return $this->state(fn (array $attributes) => [
            'status'     => 'paid',
            'paid_amount' => $attributes['total_amount'] ?? fake()->randomFloat(2, 50, 500),
            'paid_at'    => now(),
        ]);
    }

    public function unpaid(): static
    {
        return $this->state(fn (array $attributes) => [
            'status'      => 'unpaid',
            'paid_amount'  => 0,
            'paid_at'      => null,
        ]);
    }
}
