<?php

namespace App\Services;

use App\Models\Coupon;
use App\Models\Cart;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class CouponService
{
    /**
     * Validate a coupon code for a cart
     *
     * @param string $code
     * @param float $cartSubtotal
     * @return array{valid: bool, coupon: Coupon|null, error: string|null}
     */
    public function validateCoupon(string $code, float $cartSubtotal): array
    {
        $coupon = Coupon::byCode($code)->first();

        if (!$coupon) {
            return [
                'valid' => false,
                'coupon' => null,
                'error' => 'Coupon not found.',
            ];
        }

        // Check if active
        if (!$coupon->is_active) {
            return [
                'valid' => false,
                'coupon' => $coupon,
                'error' => 'This coupon is no longer active.',
            ];
        }

        // Check date range
        if (!$coupon->isWithinDateRange()) {
            if ($coupon->start_date && Carbon::now()->lt($coupon->start_date)) {
                return [
                    'valid' => false,
                    'coupon' => $coupon,
                    'error' => 'This coupon is not yet valid. Valid from: ' . $coupon->start_date->format('M d, Y'),
                ];
            }

            return [
                'valid' => false,
                'coupon' => $coupon,
                'error' => 'This coupon has expired.',
            ];
        }

        // Check usage limit
        if (!$coupon->hasRemainingUsage()) {
            return [
                'valid' => false,
                'coupon' => $coupon,
                'error' => 'This coupon has reached its usage limit.',
            ];
        }

        // Check minimum order amount
        if (!$coupon->meetsMinimumOrderAmount($cartSubtotal)) {
            return [
                'valid' => false,
                'coupon' => $coupon,
                'error' => "Minimum order amount of \${$coupon->min_order_amount} required. Your cart total is \${$cartSubtotal}.",
            ];
        }

        return [
            'valid' => true,
            'coupon' => $coupon,
            'error' => null,
        ];
    }

    /**
     * Apply coupon to cart
     *
     * @param Cart $cart
     * @param string $code
     * @return array{success: bool, message: string, data: array|null}
     */
    public function applyCouponToCart(Cart $cart, string $code): array
    {
        $subtotal = $cart->getSubtotal();

        if ($subtotal <= 0) {
            return [
                'success' => false,
                'message' => 'Cannot apply coupon to empty cart.',
                'data' => null,
            ];
        }

        $validation = $this->validateCoupon($code, $subtotal);

        if (!$validation['valid']) {
            return [
                'success' => false,
                'message' => $validation['error'],
                'data' => null,
            ];
        }

        $coupon = $validation['coupon'];
        $discountAmount = $coupon->calculateDiscount($subtotal);

        // Update cart with coupon
        $cart->update([
            'coupon_id' => $coupon->id,
            'coupon_code' => $coupon->code,
            'discount_amount' => $discountAmount,
        ]);

        Log::info('Coupon applied to cart', [
            'cart_id' => $cart->id,
            'coupon_code' => $coupon->code,
            'discount_amount' => $discountAmount,
        ]);

        return [
            'success' => true,
            'message' => "Coupon '{$coupon->code}' applied successfully. You saved \${$discountAmount}!",
            'data' => [
                'coupon' => $coupon->only(['id', 'code', 'type', 'value', 'formatted_value']),
                'discount_amount' => $discountAmount,
                'cart_totals' => $this->calculateCartTotals($cart->fresh()),
            ],
        ];
    }

    /**
     * Remove coupon from cart
     *
     * @param Cart $cart
     * @return array{success: bool, message: string, data: array|null}
     */
    public function removeCouponFromCart(Cart $cart): array
    {
        if (!$cart->hasCoupon()) {
            return [
                'success' => false,
                'message' => 'No coupon applied to this cart.',
                'data' => null,
            ];
        }

        $couponCode = $cart->coupon_code;

        $cart->update([
            'coupon_id' => null,
            'coupon_code' => null,
            'discount_amount' => 0,
        ]);

        Log::info('Coupon removed from cart', [
            'cart_id' => $cart->id,
            'coupon_code' => $couponCode,
        ]);

        return [
            'success' => true,
            'message' => 'Coupon removed successfully.',
            'data' => [
                'cart_totals' => $this->calculateCartTotals($cart->fresh()),
            ],
        ];
    }

    /**
     * Calculate cart totals with discount applied
     *
     * @param Cart $cart
     * @return array
     */
    public function calculateCartTotals(Cart $cart): array
    {
        $subtotal = $cart->getSubtotal();
        $discountAmount = $cart->discount_amount !== null ? (float) $cart->discount_amount : 0.0;
        $taxRate = config('cart.tax_rate', 0);
        $taxAmount = ($subtotal - $discountAmount) * $taxRate;
        $total = $subtotal - $discountAmount + $taxAmount;

        return [
            'subtotal' => round($subtotal, 2),
            'discount_amount' => round($discountAmount, 2),
            'tax_amount' => round($taxAmount, 2),
            'tax_rate' => $taxRate,
            'total' => round($total, 2),
            'item_count' => $cart->items->sum('quantity'),
        ];
    }

    /**
     * Redeem coupon for an order (atomic operation)
     * Called when order is successfully created
     *
     * @param Coupon $coupon
     * @param User $user
     * @param int $orderId
     * @return bool
     */
    public function redeemCoupon(Coupon $coupon, User $user, int $orderId): bool
    {
        return DB::transaction(function () use ($coupon, $user, $orderId) {
            // Atomically increment usage count
            if (!$coupon->incrementUsage()) {
                Log::warning('Failed to increment coupon usage', [
                    'coupon_id' => $coupon->id,
                    'coupon_code' => $coupon->code,
                ]);
                return false;
            }

            // Record user redemption
            $coupon->users()->attach($user->id, [
                'redeemed_at' => Carbon::now(),
                'order_id' => $orderId,
            ]);

            Log::info('Coupon redeemed', [
                'coupon_code' => $coupon->code,
                'user_id' => $user->id,
                'order_id' => $orderId,
            ]);

            return true;
        });
    }

    /**
     * Revert coupon redemption (for order cancellation)
     *
     * @param string $couponCode
     * @param User $user
     * @param int $orderId
     * @return bool
     */
    public function revertCouponRedemption(string $couponCode, User $user, int $orderId): bool
    {
        return DB::transaction(function () use ($couponCode, $user, $orderId) {
            $coupon = Coupon::byCode($couponCode)->first();

            if (!$coupon) {
                return false;
            }

            // Decrement usage count
            $coupon->decrementUsage();

            // Remove user redemption record
            $coupon->users()
                ->where('user_id', $user->id)
                ->where('order_id', $orderId)
                ->detach();

            Log::info('Coupon redemption reverted', [
                'coupon_code' => $couponCode,
                'user_id' => $user->id,
                'order_id' => $orderId,
            ]);

            return true;
        });
    }

    /**
     * Get coupon usage statistics
     *
     * @param Coupon $coupon
     * @return array
     */
    public function getCouponStats(Coupon $coupon): array
    {
        return [
            'total_used' => $coupon->used_count,
            'remaining' => $coupon->getRemainingUsage(),
            'usage_limit' => $coupon->usage_limit,
            'unique_users' => $coupon->users()->count(),
            'recent_redemptions' => $coupon->users()
                ->orderByPivot('redeemed_at', 'desc')
                ->limit(10)
                ->get(['users.id', 'users.name', 'users.email', 'coupon_user.redeemed_at', 'coupon_user.order_id'])
                ->toArray(),
        ];
    }

    /**
     * Get available coupons for a user (not yet redeemed)
     *
     * @param User $user
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public function getAvailableCouponsForUser(User $user)
    {
        $redeemedCouponIds = $user->coupons()->pluck('coupon_id')->toArray();

        return Coupon::valid()
            ->whereNotIn('id', $redeemedCouponIds)
            ->get();
    }

    /**
     * Get user's redeemed coupons
     *
     * @param User $user
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public function getUserRedeemedCoupons(User $user)
    {
        return $user->coupons()
            ->withPivot(['redeemed_at', 'order_id'])
            ->orderByPivot('redeemed_at', 'desc')
            ->get();
    }
}
