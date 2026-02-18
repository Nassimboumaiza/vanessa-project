<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Models\Product;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

class ProductRepository
{
    /**
     * Get paginated products with filters.
     */
    public function getPaginated(array $filters = [], int $perPage = 12): LengthAwarePaginator
    {
        $query = Product::query()->with(['category', 'images', 'reviews'])->where('is_active', true);

        // Apply filters
        if (! empty($filters['category'])) {
            $query->whereHas('category', function ($q) use ($filters): void {
                $q->where('slug', $filters['category']);
            });
        }

        if (! empty($filters['min_price'])) {
            $query->where('price', '>=', $filters['min_price']);
        }

        if (! empty($filters['max_price'])) {
            $query->where('price', '<=', $filters['max_price']);
        }

        if (! empty($filters['search'])) {
            $query->where(function ($q) use ($filters): void {
                $q->where('name', 'like', '%'.$filters['search'].'%')
                    ->orWhere('description', 'like', '%'.$filters['search'].'%')
                    ->orWhere('notes', 'like', '%'.$filters['search'].'%');
            });
        }

        // Sorting
        $sortBy = $filters['sort_by'] ?? 'created_at';
        $sortOrder = $filters['sort_order'] ?? 'desc';
        $query->orderBy($sortBy, $sortOrder);

        return $query->paginate($perPage);
    }

    /**
     * Find product by slug.
     */
    public function findBySlug(string $slug): ?Product
    {
        return Product::query()
            ->with(['category', 'images', 'reviews.user', 'variants'])
            ->where('slug', $slug)
            ->where('is_active', true)
            ->first();
    }

    /**
     * Get featured products.
     */
    public function getFeatured(int $limit = 8): Collection
    {
        return Product::query()
            ->with(['category', 'images'])
            ->where('is_active', true)
            ->where('is_featured', true)
            ->orderBy('created_at', 'desc')
            ->limit($limit)
            ->get();
    }

    /**
     * Get new arrivals.
     */
    public function getNewArrivals(int $limit = 8): Collection
    {
        return Product::query()
            ->with(['category', 'images'])
            ->where('is_active', true)
            ->where('is_new', true)
            ->orderBy('created_at', 'desc')
            ->limit($limit)
            ->get();
    }

    /**
     * Get related products.
     */
    public function getRelated(Product $product, int $limit = 4): Collection
    {
        return Product::query()
            ->with(['category', 'images'])
            ->where('is_active', true)
            ->where('id', '!=', $product->id)
            ->where(function ($q) use ($product): void {
                $q->where('category_id', $product->category_id)
                    ->orWhereJsonContains('notes', $product->notes);
            })
            ->orderBy('created_at', 'desc')
            ->limit($limit)
            ->get();
    }

    /**
     * Search products.
     */
    public function search(string $query, int $perPage = 12): LengthAwarePaginator
    {
        return Product::query()
            ->with(['category', 'images'])
            ->where('is_active', true)
            ->where(function ($q) use ($query): void {
                $q->where('name', 'like', '%'.$query.'%')
                    ->orWhere('description', 'like', '%'.$query.'%')
                    ->orWhere('notes', 'like', '%'.$query.'%')
                    ->orWhereHas('category', function ($cat) use ($query): void {
                        $cat->where('name', 'like', '%'.$query.'%');
                    });
            })
            ->paginate($perPage);
    }

    /**
     * Get products by category.
     */
    public function getByCategory(string $categorySlug, array $filters = [], int $perPage = 12): LengthAwarePaginator
    {
        $filters['category'] = $categorySlug;

        return $this->getPaginated($filters, $perPage);
    }
}
