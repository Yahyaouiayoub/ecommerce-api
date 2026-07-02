<?php

namespace Database\Factories;

use App\Models\Product;
use App\Models\Category;
use App\Models\Brand;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Product>
 */
class ProductFactory extends Factory
{
    protected static ?string $lastSku = null;

    public function definition(): array
    {
        $name = fake()->unique()->words(3, true);
        static::$lastSku = strtoupper(substr(md5($name), 0, 8));

        return [
            'category_id'      => Category::factory(),
            'brand_id'         => Brand::factory(),
            'name'             => ucfirst($name),
            'slug'             => Str::slug($name),
            'price'            => fake()->randomFloat(2, 10, 500),
            'purchase_price'   => fake()->randomFloat(2, 5, 250),
            'stock'            => fake()->numberBetween(0, 100),
            'sku'              => static::$lastSku,
            'is_active'        => true,
            'featured'         => false,
            'description'      => fake()->paragraph(),
            'thumbnail'        => '/test.jpg',
        ];
    }

    public function configure(): static
    {
        return $this->afterCreating(function (Product $product) {
            $finalPrice = Product::calculateFinalPrice(
                (float) $product->purchase_price,
                (float) $product->margin_percentage
            );
            if ($finalPrice > 0 && (float) $product->final_price === 0.0) {
                $product->updateQuietly(['final_price' => $finalPrice]);
            }
        });
    }

    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => false,
        ]);
    }

    public function featured(): static
    {
        return $this->state(fn (array $attributes) => [
            'featured' => true,
        ]);
    }

    public function outOfStock(): static
    {
        return $this->state(fn (array $attributes) => [
            'stock' => 0,
        ]);
    }

    public function withDiscount(float $discountPrice): static
    {
        return $this->state(fn (array $attributes) => [
            'discount_price' => $discountPrice,
        ]);
    }
}
