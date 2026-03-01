<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Review;
use App\Models\Product;
use App\Models\User;
use App\Models\Order;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\DB;

class ReviewService
{
    /**
     * Create a new review.
     *
     * @param array<string, mixed> $data
     * @throws \RuntimeException
     */
    public function createReview(User $user, Product $product, array $data): Review
    {
        return DB::transaction(function () use ($user, $product, $data) {
            // Check if user has already reviewed this product
            $existingReview = Review::query()
                ->where('user_id', $user->id)
                ->where('product_id', $product->id)
                ->first();

            if ($existingReview) {
                throw new \RuntimeException('You have already reviewed this product');
            }

            // Verify user has purchased this product
            if (! $this->hasUserPurchasedProduct($user, $product)) {
                throw new \RuntimeException('You can only review products you have purchased');
            }

            $review = Review::create([
                'user_id' => $user->id,
                'product_id' => $product->id,
                'order_id' => $data['order_id'] ?? null,
                'rating' => $data['rating'],
                'title' => $data['title'] ?? null,
                'comment' => $data['comment'],
                'is_approved' => false, // Requires admin approval
            ]);

            // Update product average rating
            $this->updateProductAverageRating($product);

            return $review->fresh(['user', 'product']);
        });
    }

    /**
     * Update existing review.
     *
     * @param array<string, mixed> $data
     */
    public function updateReview(Review $review, array $data): Review
    {
        return DB::transaction(function () use ($review, $data) {
            $updateData = [];

            if (isset($data['rating'])) {
                $updateData['rating'] = $data['rating'];
            }
            if (isset($data['title'])) {
                $updateData['title'] = $data['title'];
            }
            if (isset($data['comment'])) {
                $updateData['comment'] = $data['comment'];
            }

            // Reset approval if content changed
            if (! empty($updateData)) {
                $updateData['is_approved'] = false;
            }

            $review->update($updateData);

            // Update product average rating
            $this->updateProductAverageRating($review->product);

            return $review->fresh();
        });
    }

    /**
     * Delete review.
     */
    public function deleteReview(Review $review): bool
    {
        return DB::transaction(function () use ($review) {
            $product = $review->product;
            $review->delete();

            // Update product average rating
            $this->updateProductAverageRating($product);

            return true;
        });
    }

    /**
     * Approve review.
     */
    public function approveReview(Review $review): Review
    {
        $review->update(['is_approved' => true]);

        return $review->fresh();
    }

    /**
     * Get product reviews.
     */
    public function getProductReviews(Product $product, bool $approvedOnly = true, int $perPage = 10): LengthAwarePaginator
    {
        $query = Review::query()
            ->where('product_id', $product->id)
            ->with('user');

        if ($approvedOnly) {
            $query->where('is_approved', true);
        }

        return $query->orderBy('created_at', 'desc')->paginate($perPage);
    }

    /**
     * Get approved product reviews.
     */
    public function getApprovedProductReviews(Product $product, int $perPage = 10): LengthAwarePaginator
    {
        return $this->getProductReviews($product, true, $perPage);
    }

    /**
     * Get user reviews.
     */
    public function getUserReviews(User $user, int $perPage = 10): LengthAwarePaginator
    {
        return Review::query()
            ->where('user_id', $user->id)
            ->with('product')
            ->orderBy('created_at', 'desc')
            ->paginate($perPage);
    }

    /**
     * Get pending reviews for admin.
     */
    public function getPendingReviews(int $perPage = 15): LengthAwarePaginator
    {
        return Review::query()
            ->where('is_approved', false)
            ->with(['user', 'product'])
            ->orderBy('created_at', 'desc')
            ->paginate($perPage);
    }

    /**
     * Get all reviews for admin.
     *
     * @param array<string, mixed> $filters
     */
    public function getPaginatedReviews(array $filters = [], int $perPage = 15): LengthAwarePaginator
    {
        $query = Review::query()->with(['user', 'product']);

        if (isset($filters['is_approved'])) {
            $query->where('is_approved', $filters['is_approved']);
        }

        if (isset($filters['rating'])) {
            $query->where('rating', $filters['rating']);
        }

        if (! empty($filters['search'])) {
            $query->where(function ($q) use ($filters): void {
                $q->where('comment', 'like', "%{$filters['search']}%")
                    ->orWhere('title', 'like', "%{$filters['search']}%");
            });
        }

        $sortBy = $filters['sort_by'] ?? 'created_at';
        $sortOrder = $filters['sort_order'] ?? 'desc';

        return $query->orderBy($sortBy, $sortOrder)->paginate($perPage);
    }

    /**
     * Get review by ID.
     *
     * @throws ModelNotFoundException
     */
    public function findById(int $id): Review
    {
        return Review::query()->findOrFail($id);
    }

    /**
     * Calculate product average rating.
     */
    public function calculateProductAverageRating(Product $product): float
    {
        return Review::query()
            ->where('product_id', $product->id)
            ->where('is_approved', true)
            ->avg('rating') ?? 0.0;
    }

    /**
     * Update product average rating.
     */
    public function updateProductAverageRating(Product $product): void
    {
        $averageRating = $this->calculateProductAverageRating($product);
        $reviewCount = Review::query()
            ->where('product_id', $product->id)
            ->where('is_approved', true)
            ->count();

        $product->update([
            'average_rating' => $averageRating,
            'review_count' => $reviewCount,
        ]);
    }

    /**
     * Get product rating distribution.
     *
     * @return array<int, int>
     */
    public function getProductRatingDistribution(Product $product): array
    {
        $distribution = Review::query()
            ->where('product_id', $product->id)
            ->where('is_approved', true)
            ->selectRaw('rating, COUNT(*) as count')
            ->groupBy('rating')
            ->pluck('count', 'rating')
            ->toArray();

        return [
            5 => $distribution[5] ?? 0,
            4 => $distribution[4] ?? 0,
            3 => $distribution[3] ?? 0,
            2 => $distribution[2] ?? 0,
            1 => $distribution[1] ?? 0,
        ];
    }

    /**
     * Check if user can review product.
     */
    public function canUserReviewProduct(User $user, Product $product): bool
    {
        // Check if already reviewed
        $existingReview = Review::query()
            ->where('user_id', $user->id)
            ->where('product_id', $product->id)
            ->exists();

        if ($existingReview) {
            return false;
        }

        // Check if purchased
        return $this->hasUserPurchasedProduct($user, $product);
    }

    /**
     * Check if user has purchased product.
     */
    private function hasUserPurchasedProduct(User $user, Product $product): bool
    {
        return Order::query()
            ->where('user_id', $user->id)
            ->where('status', 'delivered')
            ->whereHas('items', function ($query) use ($product): void {
                $query->where('product_id', $product->id);
            })
            ->exists();
    }
}
