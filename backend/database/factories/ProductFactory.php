<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Category;
use App\Models\Product;
use App\Models\ProductImage;
use App\Models\ProductVariant;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Product>
 */
class ProductFactory extends Factory
{
    protected $model = Product::class;

    public function definition(): array
    {
        $name = fake()->words(3, true);

        return [
            'name' => $name,
            'slug' => Str::slug($name),
            'description' => fake()->paragraphs(3, true),
            'short_description' => fake()->paragraph(),
            'category_id' => Category::factory(),
            'price' => fake()->randomFloat(2, 50, 500),
            'compare_price' => fake()->optional()->randomFloat(2, 60, 600),
            'cost_price' => fake()->optional()->randomFloat(2, 30, 300),
            'stock_quantity' => fake()->numberBetween(0, 100),
            'sku' => strtoupper(fake()->unique()->bothify('PRD-####??')),
            'barcode' => fake()->optional()->ean13(),
            'weight' => fake()->optional()->randomFloat(2, 0.1, 2),
            'dimensions' => null,
            'notes' => null,
            'concentration' => fake()->randomElement(['Eau de Parfum', 'Eau de Toilette', 'Eau de Cologne', 'Parfum']),
            'volume_ml' => fake()->randomElement([30, 50, 100, 150, 200]),
            'country_of_origin' => fake()->optional()->country(),
            'brand' => fake()->optional()->company(),
            'perfumer' => fake()->optional()->name(),
            'release_year' => fake()->optional()->year(),
            'gender' => fake()->randomElement(['unisex', 'masculine', 'feminine']),
            'is_active' => true,
            'is_featured' => false,
            'is_new' => false,
            'rating_average' => 0,
            'rating_count' => 0,
            'meta_title' => null,
            'meta_description' => null,
        ];
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
            'is_featured' => true,
        ]);
    }

    public function markAsNew(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_new' => true,
        ]);
    }

    public function withCategory(Category $category): static
    {
        return $this->state(fn (array $attributes) => [
            'category_id' => $category->id,
        ]);
    }

    public function outOfStock(): static
    {
        return $this->state(fn (array $attributes) => [
            'stock_quantity' => 0,
        ]);
    }

    public function withVariants(int $count = 2): static
    {
        return $this->afterCreating(function (Product $product) use ($count) {
            ProductVariant::factory()->count($count)->create([
                'product_id' => $product->id,
            ]);
        });
    }

    public function withImages(int $count = 2): static
    {
        return $this->afterCreating(function (Product $product) use ($count) {
            ProductImage::factory()->count($count)->create([
                'product_id' => $product->id,
            ]);
        });
    }
}
