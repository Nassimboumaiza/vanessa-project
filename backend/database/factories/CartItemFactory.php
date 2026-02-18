<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Cart;
use App\Models\CartItem;
use App\Models\Product;
use App\Models\ProductVariant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\CartItem>
 */
class CartItemFactory extends Factory
{
    protected $model = CartItem::class;

    public function definition(): array
    {
        $quantity = fake()->numberBetween(1, 5);
        $unitPrice = fake()->randomFloat(2, 50, 300);

        return [
            'cart_id' => Cart::factory(),
            'product_id' => Product::factory(),
            'variant_id' => null,
            'quantity' => $quantity,
            'unit_price' => $unitPrice,
            'total_price' => $quantity * $unitPrice,
        ];
    }

    public function withVariant(?ProductVariant $variant = null): static
    {
        return $this->state(fn (array $attributes) => [
            'variant_id' => $variant?->id ?? ProductVariant::factory(),
        ]);
    }
}
