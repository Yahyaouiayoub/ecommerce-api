<?php

namespace Database\Factories;

use App\Models\Expense;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Expense>
 */
class ExpenseFactory extends Factory
{
    public function definition(): array
    {
        return [
            'title'        => fake()->sentence(3),
            'amount'       => fake()->randomFloat(2, 10, 5000),
            'category'     => fake()->randomElement(['salaries', 'rent', 'marketing', 'supplies', 'utilities', 'other']),
            'description'  => fake()->optional()->sentence(),
            'note'         => fake()->optional()->sentence(),
            'expense_date' => fake()->dateTimeBetween('-6 months', 'now'),
            'created_by'   => User::factory(),
        ];
    }

    public function category(string $category): static
    {
        return $this->state(fn (array $attributes) => [
            'category' => $category,
        ]);
    }
}
