<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\StoreCouponRequest;
use App\Http\Requests\Api\V1\UpdateCouponRequest;
use App\Models\Coupon;
use App\Services\CouponService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Resources\Json\JsonResource;

class CouponController extends Controller
{
    public function __construct(
        private readonly CouponService $couponService
    ) {}

    /**
     * List all coupons with filtering
     */
    public function index(Request $request): JsonResponse
    {
        $query = Coupon::query()->latest();

        // Filter by active status
        if ($request->has('is_active')) {
            $query->where('is_active', $request->boolean('is_active'));
        }

        // Filter by type
        if ($request->has('type')) {
            $query->where('type', $request->type);
        }

        // Filter by validity
        if ($request->boolean('valid_only')) {
            $query->valid();
        }

        // Filter by expired
        if ($request->boolean('expired_only')) {
            $query->expired();
        }

        // Search by code
        if ($request->has('search')) {
            $query->where('code', 'like', '%' . $request->search . '%');
        }

        $coupons = $query->paginate($request->input('per_page', 15));

        return response()->json([
            'success' => true,
            'message' => 'Coupons retrieved successfully.',
            'data' => $coupons,
        ]);
    }

    /**
     * Create a new coupon
     */
    public function store(StoreCouponRequest $request): JsonResponse
    {
        $coupon = Coupon::create($request->validated());

        return response()->json([
            'success' => true,
            'message' => 'Coupon created successfully.',
            'data' => $coupon,
        ], 201);
    }

    /**
     * Get a single coupon with statistics
     */
    public function show(Coupon $coupon): JsonResponse
    {
        $stats = $this->couponService->getCouponStats($coupon);

        return response()->json([
            'success' => true,
            'message' => 'Coupon retrieved successfully.',
            'data' => [
                'coupon' => $coupon,
                'statistics' => $stats,
            ],
        ]);
    }

    /**
     * Update a coupon
     */
    public function update(UpdateCouponRequest $request, Coupon $coupon): JsonResponse
    {
        $coupon->update($request->validated());

        return response()->json([
            'success' => true,
            'message' => 'Coupon updated successfully.',
            'data' => $coupon->fresh(),
        ]);
    }

    /**
     * Delete a coupon
     */
    public function destroy(Coupon $coupon): JsonResponse
    {
        // Check if coupon has been used
        if ($coupon->used_count > 0) {
            return response()->json([
                'success' => false,
                'message' => 'Cannot delete coupon that has been used. Consider deactivating it instead.',
                'data' => null,
            ], 422);
        }

        $coupon->delete();

        return response()->json([
            'success' => true,
            'message' => 'Coupon deleted successfully.',
            'data' => null,
        ]);
    }

    /**
     * Toggle coupon active status
     */
    public function toggleActive(Coupon $coupon): JsonResponse
    {
        $coupon->update(['is_active' => !$coupon->is_active]);

        $status = $coupon->is_active ? 'activated' : 'deactivated';

        return response()->json([
            'success' => true,
            'message' => "Coupon {$status} successfully.",
            'data' => $coupon->fresh(),
        ]);
    }

    /**
     * Get coupon usage statistics
     */
    public function statistics(Coupon $coupon): JsonResponse
    {
        $stats = $this->couponService->getCouponStats($coupon);

        return response()->json([
            'success' => true,
            'message' => 'Coupon statistics retrieved successfully.',
            'data' => $stats,
        ]);
    }

    /**
     * Get all coupon statistics summary
     */
    public function summary(): JsonResponse
    {
        $totalCoupons = Coupon::count();
        $activeCoupons = Coupon::active()->count();
        $expiredCoupons = Coupon::expired()->count();
        $totalRedemptions = Coupon::sum('used_count');

        return response()->json([
            'success' => true,
            'message' => 'Coupon summary retrieved successfully.',
            'data' => [
                'total_coupons' => $totalCoupons,
                'active_coupons' => $activeCoupons,
                'expired_coupons' => $expiredCoupons,
                'total_redemptions' => $totalRedemptions,
            ],
        ]);
    }
}
