<?php

namespace Database\Factories;

use App\Models\Refund;
use App\Models\Order;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Refund>
 */
class RefundFactory extends Factory
{
    public function definition(): array
    {
        return [
            'order_id'      => Order::factory(),
            'user_id'       => User::factory(),
            'refund_amount' => fake()->randomFloat(2, 10, 200),
            'reason'        => fake()->randomElement([
                'defective', 'not_as_described', 'wrong_size',
                'changed_mind', 'arrived_late', 'other',
            ]),
            'status'           => 'pending',
            'requested_at'     => now(),
        ];
    }

    public function pending(): static
    {
        return $this->state(fn (array $attributes) => ['status' => 'pending']);
    }

    public function approved(): static
    {
        return $this->state(fn (array $attributes) => [
            'status'            => 'approved',
            'approved_at'       => now(),
            'admin_notes'       => 'Approved by admin.',
        ]);
    }

    public function rejected(): static
    {
        return $this->state(fn (array $attributes) => [
            'status'            => 'rejected',
            'rejected_at'       => now(),
            'rejection_reason'  => 'Does not meet our return policy criteria.',
            'admin_notes'       => 'Rejected by admin.',
        ]);
    }

    public function completed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status'        => 'completed',
            'approved_at'   => now(),
            'completed_at'  => now(),
            'admin_notes'   => 'Refund processed successfully.',
        ]);
    }
}
