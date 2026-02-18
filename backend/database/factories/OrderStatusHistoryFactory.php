<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Order;
use App\Models\OrderStatusHistory;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\OrderStatusHistory>
 */
class OrderStatusHistoryFactory extends Factory
{
    protected $model = OrderStatusHistory::class;

    public function definition(): array
    {
        return [
            'order_id' => Order::factory(),
            'status' => fake()->randomElement(['pending', 'processing', 'shipped', 'delivered', 'cancelled', 'refunded']),
            'notes' => fake()->optional()->paragraph(),
            'user_id' => User::factory(),
        ];
    }
}
