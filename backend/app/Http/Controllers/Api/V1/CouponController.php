<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\ApplyCouponRequest;
use App\Models\Cart;
use App\Services\CartService;
use App\Services\CouponService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CouponController extends Controller
{
    public function __construct(
        private readonly CartService $cartService,
        private readonly CouponService $couponService
    ) {}

    /**
     * Apply coupon to current user's cart
     */
    public function apply(ApplyCouponRequest $request): JsonResponse
    {
        $user = $request->user();
        $cart = Cart::where('user_id', $user->id)->with('items')->first();

        if (!$cart || $cart->items->isEmpty()) {
            return response()->json([
                'success' => false,
                'message' => 'Your cart is empty.',
                'data' => null,
            ], 400);
        }

        $result = $this->cartService->applyCoupon($cart, $request->code);

        return response()->json($result, $result['success'] ? 200 : 422);
    }

    /**
     * Remove coupon from current user's cart
     */
    public function remove(Request $request): JsonResponse
    {
        $user = $request->user();
        $cart = Cart::where('user_id', $user->id)->first();

        if (!$cart) {
            return response()->json([
                'success' => false,
                'message' => 'Cart not found.',
                'data' => null,
            ], 404);
        }

        $result = $this->cartService->removeCoupon($cart);

        return response()->json($result);
    }

    /**
     * Validate a coupon code without applying it
     */
    public function validate(Request $request): JsonResponse
    {
        $request->validate([
            'code' => ['required', 'string', 'max:50'],
            'subtotal' => ['required', 'numeric', 'min:0'],
        ]);

        $validation = $this->couponService->validateCoupon(
            $request->code,
            (float) $request->subtotal
        );

        if ($validation['valid']) {
            $coupon = $validation['coupon'];
            $discount = $coupon->calculateDiscount((float) $request->subtotal);

            return response()->json([
                'success' => true,
                'message' => 'Coupon is valid.',
                'data' => [
                    'coupon' => $coupon->only(['id', 'code', 'type', 'value', 'formatted_value']),
                    'discount_amount' => $discount,
                    'min_order_amount' => $coupon->min_order_amount,
                ],
            ]);
        }

        return response()->json([
            'success' => false,
            'message' => $validation['error'],
            'data' => null,
        ], 422);
    }

    /**
     * Get available coupons for the user
     */
    public function available(Request $request): JsonResponse
    {
        $coupons = $this->couponService->getAvailableCouponsForUser($request->user());

        return response()->json([
            'success' => true,
            'message' => 'Available coupons retrieved successfully.',
            'data' => $coupons->map(fn($coupon) => [
                'id' => $coupon->id,
                'code' => $coupon->code,
                'type' => $coupon->type,
                'value' => $coupon->value,
                'formatted_value' => $coupon->formatted_value,
                'min_order_amount' => $coupon->min_order_amount,
                'end_date' => $coupon->end_date?->format('Y-m-d H:i:s'),
                'description' => $coupon->description,
            ]),
        ]);
    }

    /**
     * Get user's redeemed coupons
     */
    public function redeemed(Request $request): JsonResponse
    {
        $coupons = $this->couponService->getUserRedeemedCoupons($request->user());

        return response()->json([
            'success' => true,
            'message' => 'Redeemed coupons retrieved successfully.',
            'data' => $coupons->map(fn($coupon) => [
                'id' => $coupon->id,
                'code' => $coupon->code,
                'type' => $coupon->type,
                'value' => $coupon->value,
                'formatted_value' => $coupon->formatted_value,
                'redeemed_at' => $coupon->pivot->redeemed_at->format('Y-m-d H:i:s'),
                'order_id' => $coupon->pivot->order_id,
            ]),
        ]);
    }
}
