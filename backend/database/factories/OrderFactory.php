<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Order>
 */
class OrderFactory extends Factory
{
    protected $model = Order::class;

    public function definition(): array
    {
        $subtotal = fake()->randomFloat(2, 100, 1000);
        $shipping = $subtotal > 100 ? 0 : 15;
        $tax = $subtotal * 0.1;
        $total = $subtotal + $shipping + $tax;
        
        return [
            'order_number' => 'VP-' . now()->format('Ymd') . '-' . strtoupper(Str::random(4)),
            'user_id' => User::factory(),
            'status' => fake()->randomElement(['pending', 'processing', 'shipped', 'delivered', 'cancelled', 'refunded']),
            'payment_status' => fake()->randomElement(['pending', 'paid', 'failed', 'refunded']),
            'payment_method' => fake()->randomElement(['credit_card', 'paypal', 'bank_transfer']),
            'payment_transaction_id' => fake()->optional()->uuid(),
            'currency' => 'USD',
            'subtotal' => $subtotal,
            'discount_amount' => 0,
            'shipping_amount' => $shipping,
            'tax_amount' => $tax,
            'total_amount' => $total,
            'shipping_first_name' => fake()->firstName(),
            'shipping_last_name' => fake()->lastName(),
            'shipping_company' => fake()->optional()->company(),
            'shipping_address_line_1' => fake()->streetAddress(),
            'shipping_address_line_2' => fake()->optional()->secondaryAddress(),
            'shipping_city' => fake()->city(),
            'shipping_state' => fake()->state(),
            'shipping_postal_code' => fake()->postcode(),
            'shipping_country' => fake()->country(),
            'shipping_phone' => fake()->optional()->phoneNumber(),
            'billing_first_name' => fake()->firstName(),
            'billing_last_name' => fake()->lastName(),
            'billing_company' => fake()->optional()->company(),
            'billing_address_line_1' => fake()->streetAddress(),
            'billing_address_line_2' => fake()->optional()->secondaryAddress(),
            'billing_city' => fake()->city(),
            'billing_state' => fake()->state(),
            'billing_postal_code' => fake()->postcode(),
            'billing_country' => fake()->country(),
            'billing_phone' => fake()->optional()->phoneNumber(),
            'customer_notes' => fake()->optional()->paragraph(),
            'coupon_code' => fake()->optional()->bothify('COUPON-####'),
            'tracking_number' => fake()->optional()->bothify('TRACK-########'),
            'carrier' => fake()->optional()->company(),
        ];
    }

    public function pending(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'pending',
        ]);
    }

    public function processing(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'processing',
        ]);
    }

    public function shipped(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'shipped',
            'shipped_at' => now(),
        ]);
    }

    public function delivered(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'delivered',
            'delivered_at' => now(),
        ]);
    }

    public function cancelled(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'cancelled',
        ]);
    }

    public function paid(): static
    {
        return $this->state(fn (array $attributes) => [
            'payment_status' => 'paid',
        ]);
    }

    public function forUser(User $user): static
    {
        return $this->state(fn (array $attributes) => [
            'user_id' => $user->id,
        ]);
    }

    public function withItems(int $count = 2): static
    {
        return $this->afterCreating(function (Order $order) use ($count) {
            OrderItem::factory()->count($count)->create([
                'order_id' => $order->id,
            ]);
        });
    }
}
