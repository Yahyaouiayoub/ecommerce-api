<?php

namespace Database\Factories;

use App\Models\Coupon;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Coupon>
 */
class CouponFactory extends Factory
{
    public function definition(): array
    {
        return [
            'code'               => strtoupper(fake()->unique()->bothify('??????##')),
            'type'               => fake()->randomElement(['percentage', 'fixed']),
            'value'              => fake()->randomFloat(2, 5, 50),
            'is_active'          => true,
            'is_auto_apply'      => false,
            'applies_to'         => 'all',
            'usage_limit'        => null,
            'per_customer_limit' => 1,
        ];
    }

    public function percentage(): static
    {
        return $this->state(fn (array $attributes) => [
            'type'  => 'percentage',
            'value' => fake()->randomFloat(2, 5, 50),
        ]);
    }

    public function fixed(float $value = null): static
    {
        return $this->state(fn (array $attributes) => [
            'type'  => 'fixed',
            'value' => $value ?? fake()->randomFloat(2, 5, 100),
        ]);
    }

    public function expired(): static
    {
        return $this->state(fn (array $attributes) => [
            'expires_at' => now()->subDay(),
        ]);
    }

    public function autoApply(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_auto_apply' => true,
        ]);
    }

    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => false,
        ]);
    }
}
