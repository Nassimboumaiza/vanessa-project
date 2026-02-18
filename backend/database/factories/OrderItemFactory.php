<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\ProductVariant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\OrderItem>
 */
class OrderItemFactory extends Factory
{
    protected $model = OrderItem::class;

    public function definition(): array
    {
        $quantity = fake()->numberBetween(1, 5);
        $unitPrice = fake()->randomFloat(2, 50, 300);
        $tax = $unitPrice * $quantity * 0.1;
        $totalPrice = $unitPrice * $quantity;
        $product = Product::factory()->create();

        return [
            'order_id' => Order::factory(),
            'product_id' => $product->id,
            'variant_id' => null,
            'product_name' => $product->name,
            'product_sku' => $product->sku,
            'variant_name' => null,
            'quantity' => $quantity,
            'unit_price' => $unitPrice,
            'discount_amount' => 0,
            'tax_amount' => $tax,
            'total_price' => $totalPrice,
        ];
    }

    public function withVariant(?ProductVariant $variant = null): static
    {
        return $this->state(function (array $attributes) use ($variant) {
            $variant = $variant ?? ProductVariant::factory()->create();

            return [
                'variant_id' => $variant->id,
                'variant_name' => $variant->name,
            ];
        });
    }
}
