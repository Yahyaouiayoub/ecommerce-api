<?php

namespace Database\Factories;

use App\Models\Address;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Address>
 */
class AddressFactory extends Factory
{
    public function definition(): array
    {
        return [
            'user_id'       => User::factory(),
            'full_name'     => fake()->name(),
            'email'         => fake()->safeEmail(),
            'phone'         => '06' . fake()->numerify('########'),
            'address_line1' => fake()->streetAddress(),
            'address_line2' => fake()->secondaryAddress(),
            'city'          => fake()->city(),
            'state'         => fake()->state(),
            'postal_code'   => fake()->postcode(),
            'country'       => fake()->country(),
            'is_default'    => false,
            'label'         => fake()->randomElement(['Home', 'Work', 'Other']),
        ];
    }

    public function default(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_default' => true,
        ]);
    }
}
