<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\BaseController;
use App\Http\Requests\Api\V1\StoreReviewRequest;
use App\Http\Requests\Api\V1\UpdateReviewRequest;
use App\Http\Resources\Api\V1\ReviewResource;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\Review;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ReviewController extends BaseController
{
    /**
     * Create a new review.
     */
    public function store(StoreReviewRequest $request, int $productId): JsonResponse
    {
        $validated = $request->validated();

        $product = Product::findOrFail($productId);

        if (! $product->is_active) {
            return $this->errorResponse('Cannot review inactive product', 400);
        }

        // Check if user already reviewed this product
        $existingReview = Review::where('user_id', $request->user()->id)
            ->where('product_id', $productId)
            ->first();

        if ($existingReview) {
            return $this->errorResponse('You have already reviewed this product', 422);
        }

        // Verify purchase if order_id provided
        $isVerifiedPurchase = false;
        if ($validated['order_id']) {
            $order = Order::where('user_id', $request->user()->id)
                ->where('id', $validated['order_id'])
                ->where('status', 'delivered')
                ->first();

            if (! $order) {
                return $this->errorResponse('Invalid order for review', 422);
            }

            $orderItem = OrderItem::where('order_id', $order->id)
                ->where('product_id', $productId)
                ->first();

            if (! $orderItem) {
                return $this->errorResponse('Product not found in this order', 422);
            }

            $isVerifiedPurchase = true;
        }

        $review = Review::create([
            'user_id' => $request->user()->id,
            'product_id' => $productId,
            'order_id' => $validated['order_id'] ?? null,
            'rating' => $validated['rating'],
            'title' => $validated['title'] ?? null,
            'content' => $validated['content'],
            'pros' => $validated['pros'] ?? null,
            'cons' => $validated['cons'] ?? null,
            'is_verified_purchase' => $isVerifiedPurchase,
            'is_approved' => false, // Requires admin approval
        ]);

        // Update product rating
        $this->updateProductRating($product);

        return $this->successResponse(new ReviewResource($review), 'Review submitted successfully', 201);
    }

    /**
     * Update review.
     */
    public function update(UpdateReviewRequest $request, int $id): JsonResponse
    {
        $review = Review::where('user_id', $request->user()->id)
            ->where('id', $id)
            ->first();

        if (! $review) {
            return $this->errorResponse('Review not found', 404);
        }

        $validated = $request->validated();

        $review->update(array_filter($validated, fn ($value) => $value !== null));

        // Update product rating
        $this->updateProductRating($review->product);

        return $this->successResponse(new ReviewResource($review->fresh()), 'Review updated successfully');
    }

    /**
     * Delete review.
     */
    public function destroy(Request $request, int $id): JsonResponse
    {
        $review = Review::where('user_id', $request->user()->id)
            ->where('id', $id)
            ->first();

        if (! $review) {
            return $this->errorResponse('Review not found', 404);
        }

        $product = $review->product;
        $review->delete();

        // Update product rating
        $this->updateProductRating($product);

        return $this->successResponse([], 'Review deleted successfully');
    }

    /**
     * Update product average rating.
     */
    private function updateProductRating(Product $product): void
    {
        $stats = Review::where('product_id', $product->id)
            ->where('is_approved', true)
            ->selectRaw('AVG(rating) as average, COUNT(*) as count')
            ->first();

        $product->update([
            'rating_average' => round($stats->average ?? 0, 1),
            'rating_count' => $stats->count ?? 0,
        ]);
    }
}
