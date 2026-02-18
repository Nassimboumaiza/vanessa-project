<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Product;
use App\Models\ProductVariant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\ProductVariant>
 */
class ProductVariantFactory extends Factory
{
    protected $model = ProductVariant::class;

    public function definition(): array
    {
        return [
            'product_id' => Product::factory(),
            'name' => fake()->randomElement(['30ml', '50ml', '100ml', '150ml', '200ml']),
            'sku' => strtoupper(fake()->unique()->bothify('VAR-####??')),
            'price' => fake()->randomFloat(2, 50, 500),
            'compare_price' => fake()->optional()->randomFloat(2, 60, 600),
            'stock_quantity' => fake()->numberBetween(0, 100),
            'volume_ml' => fake()->randomElement([30, 50, 100, 150, 200]),
            'is_active' => true,
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => false,
        ]);
    }

    public function outOfStock(): static
    {
        return $this->state(fn (array $attributes) => [
            'stock_quantity' => 0,
        ]);
    }
}
