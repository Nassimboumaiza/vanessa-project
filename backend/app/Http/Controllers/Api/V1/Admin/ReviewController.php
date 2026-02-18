<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Api\BaseController;
use App\Http\Resources\Api\V1\ReviewCollection;
use App\Http\Resources\Api\V1\ReviewResource;
use App\Models\Product;
use App\Models\Review;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ReviewController extends BaseController
{
    /**
     * Display a listing of reviews.
     */
    public function index(Request $request): JsonResponse
    {
        $query = Review::with(['user', 'product', 'order']);

        if ($request->has('status')) {
            $isApproved = $request->get('status') === 'approved';
            $query->where('is_approved', $isApproved);
        }

        if ($request->has('product_id')) {
            $query->where('product_id', $request->get('product_id'));
        }

        if ($request->has('rating')) {
            $query->where('rating', $request->get('rating'));
        }

        $perPage = (int) $request->get('per_page', config('api.pagination.default_per_page'));
        $reviews = $query->orderBy('created_at', 'desc')->paginate($perPage);

        return $this->paginatedResponse(new ReviewCollection($reviews), 'Reviews retrieved successfully');
    }

    /**
     * Approve a review.
     */
    public function approve(Request $request, int $id): JsonResponse
    {
        $review = Review::findOrFail($id);

        $review->update([
            'is_approved' => true,
            'approved_at' => now(),
        ]);

        // Update product rating
        $this->updateProductRating($review->product);

        return $this->successResponse(new ReviewResource($review->fresh()), 'Review approved successfully');
    }

    /**
     * Remove the specified review.
     */
    public function destroy(int $id): JsonResponse
    {
        $review = Review::findOrFail($id);
        $product = $review->product;

        $review->delete();

        // Update product rating
        $this->updateProductRating($product);

        return $this->noContentResponse();
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
