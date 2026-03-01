<?php

namespace Database\Factories;

use App\Models\Coupon;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\Factory;

class CouponFactory extends Factory
{
    protected $model = Coupon::class;

    public function definition(): array
    {
        return [
            'code' => strtoupper(fake()->bothify('COUPON##??')),
            'type' => fake()->randomElement([Coupon::TYPE_PERCENTAGE, Coupon::TYPE_FIXED]),
            'value' => fake()->randomFloat(2, 5, 50),
            'usage_limit' => fake()->optional()->numberBetween(10, 100),
            'used_count' => 0,
            'start_date' => fake()->optional()->dateTimeBetween('-7 days', '+7 days'),
            'end_date' => fake()->optional()->dateTimeBetween('+7 days', '+30 days'),
            'min_order_amount' => fake()->randomFloat(2, 0, 100),
            'is_active' => true,
            'description' => fake()->optional()->sentence(),
        ];
    }

    public function percentage(): static
    {
        return $this->state(fn(array $attributes) => [
            'type' => Coupon::TYPE_PERCENTAGE,
        ]);
    }

    public function fixed(): static
    {
        return $this->state(fn(array $attributes) => [
            'type' => Coupon::TYPE_FIXED,
        ]);
    }

    public function active(): static
    {
        return $this->state(fn(array $attributes) => [
            'is_active' => true,
            'start_date' => Carbon::now()->subDays(5),
            'end_date' => Carbon::now()->addDays(30),
        ]);
    }

    public function expired(): static
    {
        return $this->state(fn(array $attributes) => [
            'end_date' => Carbon::now()->subDay(),
        ]);
    }

    public function upcoming(): static
    {
        return $this->state(fn(array $attributes) => [
            'start_date' => Carbon::now()->addDays(5),
            'end_date' => Carbon::now()->addDays(30),
        ]);
    }

    public function exhausted(): static
    {
        return $this->state(fn(array $attributes) => [
            'usage_limit' => 10,
            'used_count' => 10,
        ]);
    }

    public function withMinOrder(float $amount): static
    {
        return $this->state(fn(array $attributes) => [
            'min_order_amount' => $amount,
        ]);
    }
}
