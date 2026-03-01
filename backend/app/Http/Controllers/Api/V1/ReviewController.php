<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\BaseController;
use App\Http\Requests\Api\V1\StoreReviewRequest;
use App\Http\Requests\Api\V1\UpdateReviewRequest;
use App\Http\Resources\Api\V1\ReviewResource;
use App\Services\ProductService;
use App\Services\ReviewService;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ReviewController extends BaseController
{
    public function __construct(
        private readonly ReviewService $reviewService,
        private readonly ProductService $productService
    ) {}

    /**
     * Create a new review.
     */
    public function store(StoreReviewRequest $request, int $productId): JsonResponse
    {
        $validated = $request->validated();
        $user = $request->user();

        try {
            $product = $this->productService->findById($productId);
        } catch (ModelNotFoundException $e) {
            return $this->errorResponse('Product not found', 404);
        }

        if (! $product->is_active) {
            return $this->errorResponse('Cannot review inactive product', 400);
        }

        try {
            $review = $this->reviewService->createReview($user, $product, $validated);

            return $this->successResponse(new ReviewResource($review), 'Review submitted successfully', 201);
        } catch (\RuntimeException $e) {
            return $this->errorResponse($e->getMessage(), 422);
        }
    }

    /**
     * Update review.
     */
    public function update(UpdateReviewRequest $request, int $id): JsonResponse
    {
        $validated = $request->validated();
        $user = $request->user();

        try {
            $review = $this->reviewService->findById($id);

            // Ensure user owns the review
            if ($review->user_id !== $user->id) {
                return $this->errorResponse('Review not found', 404);
            }

            $updatedReview = $this->reviewService->updateReview($review, $validated);

            return $this->successResponse(new ReviewResource($updatedReview), 'Review updated successfully');
        } catch (ModelNotFoundException $e) {
            return $this->errorResponse('Review not found', 404);
        } catch (\RuntimeException $e) {
            return $this->errorResponse($e->getMessage(), 400);
        }
    }

    /**
     * Delete review.
     */
    public function destroy(Request $request, int $id): JsonResponse
    {
        $user = $request->user();

        try {
            $review = $this->reviewService->findById($id);

            // Ensure user owns the review
            if ($review->user_id !== $user->id) {
                return $this->errorResponse('Review not found', 404);
            }

            $this->reviewService->deleteReview($review);

            return $this->successResponse([], 'Review deleted successfully');
        } catch (ModelNotFoundException $e) {
            return $this->errorResponse('Review not found', 404);
        }
    }
}
