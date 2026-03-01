<?php

namespace Tests\Feature;

use App\Models\Cart;
use App\Models\CartItem;
use App\Models\Coupon;
use App\Models\Product;
use App\Models\User;
use App\Services\CouponService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class CouponServiceTest extends TestCase
{
    use RefreshDatabase;

    private CouponService $couponService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->couponService = app(CouponService::class);
    }

    /** @test */
    public function it_validates_a_valid_coupon(): void
    {
        $coupon = Coupon::factory()->create([
            'code' => 'VALID20',
            'type' => Coupon::TYPE_PERCENTAGE,
            'value' => 20,
            'is_active' => true,
            'min_order_amount' => 50,
        ]);

        $result = $this->couponService->validateCoupon('VALID20', 100);

        $this->assertTrue($result['valid']);
        $this->assertNull($result['error']);
        $this->assertEquals($coupon->id, $result['coupon']->id);
    }

    /** @test */
    public function it_rejects_nonexistent_coupon(): void
    {
        $result = $this->couponService->validateCoupon('INVALID', 100);

        $this->assertFalse($result['valid']);
        $this->assertEquals('Coupon not found.', $result['error']);
    }

    /** @test */
    public function it_rejects_inactive_coupon(): void
    {
        Coupon::factory()->create([
            'code' => 'INACTIVE',
            'is_active' => false,
        ]);

        $result = $this->couponService->validateCoupon('INACTIVE', 100);

        $this->assertFalse($result['valid']);
        $this->assertStringContainsString('no longer active', $result['error']);
    }

    /** @test */
    public function it_rejects_expired_coupon(): void
    {
        Coupon::factory()->create([
            'code' => 'EXPIRED',
            'start_date' => Carbon::now()->subDays(10),
            'end_date' => Carbon::now()->subDay(),
        ]);

        $result = $this->couponService->validateCoupon('EXPIRED', 100);

        $this->assertFalse($result['valid']);
        $this->assertStringContainsString('expired', $result['error']);
    }

    /** @test */
    public function it_rejects_future_coupon(): void
    {
        Coupon::factory()->create([
            'code' => 'FUTURE',
            'start_date' => Carbon::now()->addDays(5),
        ]);

        $result = $this->couponService->validateCoupon('FUTURE', 100);

        $this->assertFalse($result['valid']);
        $this->assertStringContainsString('not yet valid', $result['error']);
    }

    /** @test */
    public function it_rejects_exhausted_coupon(): void
    {
        Coupon::factory()->create([
            'code' => 'EXHAUSTED',
            'usage_limit' => 5,
            'used_count' => 5,
            'start_date' => now()->subDays(10),
            'end_date' => now()->addDays(10),
        ]);

        $result = $this->couponService->validateCoupon('EXHAUSTED', 100);

        $this->assertFalse($result['valid']);
        $this->assertStringContainsString('usage limit', $result['error']);
    }

    /** @test */
    public function it_rejects_coupon_below_minimum_order(): void
    {
        Coupon::factory()->active()->create([
            'code' => 'MIN50',
            'min_order_amount' => 50,
        ]);

        $result = $this->couponService->validateCoupon('MIN50', 30);

        $this->assertFalse($result['valid']);
        $this->assertStringContainsString('Minimum order amount', $result['error']);
    }

    /** @test */
    public function it_applies_coupon_to_cart(): void
    {
        $user = User::factory()->create();
        $product = Product::factory()->create(['price' => 100]);
        $cart = Cart::create(['user_id' => $user->id]);
        CartItem::create([
            'cart_id' => $cart->id,
            'product_id' => $product->id,
            'quantity' => 1,
            'unit_price' => 100,
            'total_price' => 100,
        ]);

        Coupon::factory()->active()->create([
            'code' => 'SAVE20',
            'type' => Coupon::TYPE_PERCENTAGE,
            'value' => 20,
        ]);

        $result = $this->couponService->applyCouponToCart($cart, 'SAVE20');

        $this->assertTrue($result['success']);
        $this->assertStringContainsString('applied successfully', $result['message']);
        $this->assertEquals(20.00, $result['data']['discount_amount']);

        $cart->refresh();
        $this->assertEquals('SAVE20', $cart->coupon_code);
        $this->assertEquals(20.00, $cart->discount_amount);
    }

    /** @test */
    public function it_removes_coupon_from_cart(): void
    {
        $user = User::factory()->create();
        $coupon = Coupon::factory()->create(['code' => 'SAVE20']);
        $cart = Cart::create([
            'user_id' => $user->id,
            'coupon_id' => $coupon->id,
            'coupon_code' => 'SAVE20',
            'discount_amount' => 20,
        ]);

        $result = $this->couponService->removeCouponFromCart($cart);

        $this->assertTrue($result['success']);
        $this->assertStringContainsString('removed successfully', $result['message']);

        $cart->refresh();
        $this->assertNull($cart->coupon_code);
        $this->assertEquals(0, $cart->discount_amount);
    }

    /** @test */
    public function it_calculates_cart_totals_with_discount(): void
    {
        $user = User::factory()->create();
        $product = Product::factory()->create(['price' => 100]);
        $cart = Cart::create([
            'user_id' => $user->id,
            'discount_amount' => 20,
        ]);
        CartItem::create([
            'cart_id' => $cart->id,
            'product_id' => $product->id,
            'quantity' => 1,
            'unit_price' => 100,
            'total_price' => 100,
        ]);

        $totals = $this->couponService->calculateCartTotals($cart);

        $this->assertEquals(100.00, $totals['subtotal']);
        $this->assertEquals(20.00, $totals['discount_amount']);
        // Total = subtotal - discount + tax (10% on discounted amount)
        // 100 - 20 + 8 = 88
        $this->assertEquals(88.00, $totals['total']);
    }

    /** @test */
    public function it_redeems_coupon_atomically(): void
    {
        $user = User::factory()->create();
        $coupon = Coupon::factory()->create([
            'usage_limit' => 10,
            'used_count' => 5,
        ]);

        $result = $this->couponService->redeemCoupon($coupon, $user, 1);

        $this->assertTrue($result);
        $this->assertEquals(6, $coupon->fresh()->used_count);
        $this->assertDatabaseHas('coupon_user', [
            'coupon_id' => $coupon->id,
            'user_id' => $user->id,
            'order_id' => 1,
        ]);
    }

    /** @test */
    public function it_prevents_concurrent_over_redemption(): void
    {
        $coupon = Coupon::factory()->create([
            'usage_limit' => 10,
            'used_count' => 9, // Only 1 left
        ]);

        // Simulate concurrent redemption attempts
        $results = collect();

        // Run 5 concurrent attempts
        for ($i = 0; $i < 5; $i++) {
            $results->push(DB::transaction(function () use ($coupon, $i) {
                $coupon->refresh();
                return $coupon->incrementUsage();
            }));
        }

        // Only one should succeed
        $successfulCount = $results->filter(fn($r) => $r > 0)->count();
        $this->assertLessThanOrEqual(1, $successfulCount);
        $this->assertEquals(10, $coupon->fresh()->used_count);
    }

    /** @test */
    public function it_reverts_coupon_redemption(): void
    {
        $user = User::factory()->create();
        $coupon = Coupon::factory()->create([
            'used_count' => 5,
        ]);

        // First redeem
        $this->couponService->redeemCoupon($coupon, $user, 1);
        $this->assertEquals(6, $coupon->fresh()->used_count);

        // Then revert
        $result = $this->couponService->revertCouponRedemption($coupon->code, $user, 1);

        $this->assertTrue($result);
        $this->assertEquals(5, $coupon->fresh()->used_count);
        $this->assertDatabaseMissing('coupon_user', [
            'coupon_id' => $coupon->id,
            'user_id' => $user->id,
        ]);
    }

    /** @test */
    public function it_gets_available_coupons_for_user(): void
    {
        $user = User::factory()->create();

        // Create available coupons (must be valid/active)
        Coupon::factory()->active()->create(['code' => 'AVAILABLE1']);
        Coupon::factory()->active()->create(['code' => 'AVAILABLE2']);

        // Create already redeemed coupon
        $redeemedCoupon = Coupon::factory()->active()->create(['code' => 'REDEEMED']);
        $user->coupons()->attach($redeemedCoupon->id, ['redeemed_at' => now()]);

        $available = $this->couponService->getAvailableCouponsForUser($user);

        $this->assertCount(2, $available);
        $this->assertTrue($available->pluck('code')->contains('AVAILABLE1'));
        $this->assertTrue($available->pluck('code')->contains('AVAILABLE2'));
        $this->assertFalse($available->pluck('code')->contains('REDEEMED'));
    }

    /** @test */
    public function it_gets_user_redeemed_coupons(): void
    {
        $user = User::factory()->create();

        $coupon1 = Coupon::factory()->create(['code' => 'REDEEMED1']);
        $coupon2 = Coupon::factory()->create(['code' => 'REDEEMED2']);

        $user->coupons()->attach($coupon1->id, ['redeemed_at' => now(), 'order_id' => 1]);
        $user->coupons()->attach($coupon2->id, ['redeemed_at' => now(), 'order_id' => 2]);

        $redeemed = $this->couponService->getUserRedeemedCoupons($user);

        $this->assertCount(2, $redeemed);
    }
}
