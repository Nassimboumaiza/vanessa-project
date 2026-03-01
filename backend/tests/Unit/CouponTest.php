<?php

namespace Tests\Unit;

use App\Models\Coupon;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CouponTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function it_can_create_a_percentage_coupon(): void
    {
        $coupon = Coupon::create([
            'code' => 'SAVE20',
            'type' => Coupon::TYPE_PERCENTAGE,
            'value' => 20,
            'is_active' => true,
        ]);

        $this->assertDatabaseHas('coupons', [
            'code' => 'SAVE20',
            'type' => 'percentage',
            'value' => 20,
        ]);
    }

    /** @test */
    public function it_can_create_a_fixed_amount_coupon(): void
    {
        $coupon = Coupon::create([
            'code' => 'FLAT50',
            'type' => Coupon::TYPE_FIXED,
            'value' => 50,
            'is_active' => true,
        ]);

        $this->assertDatabaseHas('coupons', [
            'code' => 'FLAT50',
            'type' => 'fixed',
            'value' => 50,
        ]);
    }

    /** @test */
    public function it_calculates_percentage_discount_correctly(): void
    {
        $coupon = Coupon::factory()->create([
            'type' => Coupon::TYPE_PERCENTAGE,
            'value' => 20,
        ]);

        $discount = $coupon->calculateDiscount(100);

        $this->assertEquals(20.00, $discount);
    }

    /** @test */
    public function it_calculates_fixed_discount_correctly(): void
    {
        $coupon = Coupon::factory()->create([
            'type' => Coupon::TYPE_FIXED,
            'value' => 25,
        ]);

        $discount = $coupon->calculateDiscount(100);

        $this->assertEquals(25.00, $discount);
    }

    /** @test */
    public function fixed_discount_cannot_exceed_subtotal(): void
    {
        $coupon = Coupon::factory()->create([
            'type' => Coupon::TYPE_FIXED,
            'value' => 150,
        ]);

        $discount = $coupon->calculateDiscount(100);

        $this->assertEquals(100.00, $discount);
    }

    /** @test */
    public function it_validates_date_range_correctly(): void
    {
        // Coupon that hasn't started yet
        $futureCoupon = Coupon::factory()->create([
            'start_date' => Carbon::now()->addDays(5),
            'end_date' => Carbon::now()->addDays(10),
        ]);

        $this->assertFalse($futureCoupon->isWithinDateRange());

        // Coupon that has expired
        $expiredCoupon = Coupon::factory()->create([
            'start_date' => Carbon::now()->subDays(10),
            'end_date' => Carbon::now()->subDays(5),
        ]);

        $this->assertFalse($expiredCoupon->isWithinDateRange());

        // Valid coupon
        $validCoupon = Coupon::factory()->create([
            'start_date' => Carbon::now()->subDays(5),
            'end_date' => Carbon::now()->addDays(5),
        ]);

        $this->assertTrue($validCoupon->isWithinDateRange());
    }

    /** @test */
    public function it_checks_usage_limit_correctly(): void
    {
        // Unlimited usage
        $unlimitedCoupon = Coupon::factory()->create([
            'usage_limit' => null,
            'used_count' => 100,
        ]);

        $this->assertTrue($unlimitedCoupon->hasRemainingUsage());

        // Limited usage with remaining
        $limitedCoupon = Coupon::factory()->create([
            'usage_limit' => 10,
            'used_count' => 5,
        ]);

        $this->assertTrue($limitedCoupon->hasRemainingUsage());

        // Limited usage exhausted
        $exhaustedCoupon = Coupon::factory()->create([
            'usage_limit' => 10,
            'used_count' => 10,
        ]);

        $this->assertFalse($exhaustedCoupon->hasRemainingUsage());
    }

    /** @test */
    public function it_checks_minimum_order_amount_correctly(): void
    {
        $coupon = Coupon::factory()->create([
            'min_order_amount' => 50,
        ]);

        $this->assertFalse($coupon->meetsMinimumOrderAmount(30));
        $this->assertTrue($coupon->meetsMinimumOrderAmount(50));
        $this->assertTrue($coupon->meetsMinimumOrderAmount(100));
    }

    /** @test */
    public function it_increments_usage_count_atomically(): void
    {
        $coupon = Coupon::factory()->create([
            'usage_limit' => 10,
            'used_count' => 5,
        ]);

        $result = $coupon->incrementUsage();

        $this->assertTrue($result);
        $this->assertEquals(6, $coupon->fresh()->used_count);
    }

    /** @test */
    public function it_prevents_over_redemption(): void
    {
        $coupon = Coupon::factory()->create([
            'usage_limit' => 10,
            'used_count' => 10,
        ]);

        $result = $coupon->incrementUsage();

        $this->assertFalse($result);
        $this->assertEquals(10, $coupon->fresh()->used_count);
    }

    /** @test */
    public function it_decrements_usage_count(): void
    {
        $coupon = Coupon::factory()->create([
            'used_count' => 5,
        ]);

        $coupon->decrementUsage();

        $this->assertEquals(4, $coupon->fresh()->used_count);
    }

    /** @test */
    public function it_returns_remaining_usage(): void
    {
        $limitedCoupon = Coupon::factory()->create([
            'usage_limit' => 10,
            'used_count' => 3,
        ]);

        $this->assertEquals(7, $limitedCoupon->getRemainingUsage());

        $unlimitedCoupon = Coupon::factory()->create([
            'usage_limit' => null,
        ]);

        $this->assertNull($unlimitedCoupon->getRemainingUsage());
    }

    /** @test */
    public function is_valid_checks_all_conditions(): void
    {
        $validCoupon = Coupon::factory()->create([
            'is_active' => true,
            'start_date' => Carbon::now()->subDays(5),
            'end_date' => Carbon::now()->addDays(5),
            'usage_limit' => 10,
            'used_count' => 5,
        ]);

        $this->assertTrue($validCoupon->isValid());

        // Inactive
        $inactiveCoupon = Coupon::factory()->create(['is_active' => false]);
        $this->assertFalse($inactiveCoupon->isValid());

        // Expired
        $expiredCoupon = Coupon::factory()->create([
            'end_date' => Carbon::now()->subDay(),
        ]);
        $this->assertFalse($expiredCoupon->isValid());

        // Exhausted
        $exhaustedCoupon = Coupon::factory()->create([
            'usage_limit' => 5,
            'used_count' => 5,
        ]);
        $this->assertFalse($exhaustedCoupon->isValid());
    }

    /** @test */
    public function formatted_value_displays_correctly(): void
    {
        $percentageCoupon = Coupon::factory()->create([
            'type' => Coupon::TYPE_PERCENTAGE,
            'value' => 25,
        ]);

        $this->assertEquals('25%', $percentageCoupon->formatted_value);

        $fixedCoupon = Coupon::factory()->create([
            'type' => Coupon::TYPE_FIXED,
            'value' => 50,
        ]);

        $this->assertEquals('$50.00', $fixedCoupon->formatted_value);
    }
}
