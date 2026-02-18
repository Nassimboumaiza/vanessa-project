<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Cart;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Cart>
 */
class CartFactory extends Factory
{
    protected $model = Cart::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'total_amount' => 0,
            'total_items' => 0,
        ];
    }

    public function withItems(int $count = 2): static
    {
        return $this->afterCreating(function (Cart $cart) use ($count) {
            CartItem::factory()->count($count)->create([
                'cart_id' => $cart->id,
            ]);

            // Update cart totals
            $cart->refresh();
            $totalAmount = $cart->items->sum('total_price');
            $totalItems = $cart->items->sum('quantity');
            $cart->update([
                'total_amount' => $totalAmount,
                'total_items' => $totalItems,
            ]);
        });
    }
}
