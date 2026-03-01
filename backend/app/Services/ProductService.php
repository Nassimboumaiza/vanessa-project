<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Product;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class ProductService
{
    /**
     * Cache tag for product-related caching.
     */
    private const CACHE_TAG = 'products';

    /**
     * Default cache TTL in seconds (1 hour).
     */
    private const DEFAULT_TTL = 3600;
    /**
     * Get paginated products with filters and sorting.
     *
     * @param array<string, mixed> $filters
     */
    public function getPaginatedProducts(array $filters = [], int $perPage = 12, bool $activeOnly = true): LengthAwarePaginator
    {
        $query = Product::query()
            ->with(['category', 'images', 'reviews']);

        if ($activeOnly) {
            $query->where('is_active', true);
        }

        $this->applyFilters($query, $filters);
        $this->applySorting($query, $filters);

        return $query->paginate($perPage);
    }

    /**
     * Find a single product by its slug with all relations.
     *
     * @throws ModelNotFoundException
     */
    public function findBySlug(string $slug): Product
    {
        $cacheKey = $this->getCacheKey('slug', $slug);

        return Cache::tags([self::CACHE_TAG, 'product_detail'])
            ->remember($cacheKey, self::DEFAULT_TTL, function () use ($slug): Product {
                return Product::query()
                    ->with(['category', 'images', 'reviews.user', 'variants'])
                    ->where('slug', $slug)
                    ->where('is_active', true)
                    ->firstOrFail();
            });
    }

    /**
     * Find a single product by its ID.
     *
     * @throws ModelNotFoundException
     */
    public function findById(int $id): Product
    {
        $cacheKey = $this->getCacheKey('id', $id);

        return Cache::tags([self::CACHE_TAG, 'product_detail'])
            ->remember($cacheKey, self::DEFAULT_TTL, function () use ($id): Product {
                return Product::query()
                    ->with(['category', 'images', 'reviews', 'variants'])
                    ->findOrFail($id);
            });
    }

    /**
     * Get featured products for homepage display.
     */
    public function getFeaturedProducts(int $limit = 8): Collection
    {
        $cacheKey = $this->getCacheKey('featured', $limit);

        return Cache::tags([self::CACHE_TAG, 'featured'])
            ->remember($cacheKey, self::DEFAULT_TTL, function () use ($limit): Collection {
                return Product::query()
                    ->with(['category', 'images'])
                    ->where('is_active', true)
                    ->where('is_featured', true)
                    ->orderBy('created_at', 'desc')
                    ->limit($limit)
                    ->get();
            });
    }

    /**
     * Get new arrival products.
     */
    public function getNewArrivals(int $limit = 8): Collection
    {
        $cacheKey = $this->getCacheKey('new_arrivals', $limit);

        return Cache::tags([self::CACHE_TAG, 'new_arrivals'])
            ->remember($cacheKey, self::DEFAULT_TTL, function () use ($limit): Collection {
                return Product::query()
                    ->with(['category', 'images'])
                    ->where('is_active', true)
                    ->where('is_new', true)
                    ->orderBy('created_at', 'desc')
                    ->limit($limit)
                    ->get();
            });
    }

    /**
     * Get related products for a given product.
     */
    public function getRelatedProducts(Product $product, int $limit = 4): Collection
    {
        $cacheKey = $this->getCacheKey('related', $product->id, $limit);

        return Cache::tags([self::CACHE_TAG, 'related'])
            ->remember($cacheKey, self::DEFAULT_TTL, function () use ($product, $limit): Collection {
                return Product::query()
                    ->with(['category', 'images'])
                    ->where('is_active', true)
                    ->where('id', '!=', $product->id)
                    ->where(function ($query) use ($product): void {
                        $query->where('category_id', $product->category_id)
                            ->orWhereJsonContains('notes', $product->notes);
                    })
                    ->orderBy('created_at', 'desc')
                    ->limit($limit)
                    ->get();
            });
    }

    /**
     * Search products by query string.
     *
     * @param array<string, mixed> $filters
     */
    public function searchProducts(string $searchQuery, array $filters = [], int $perPage = 12): LengthAwarePaginator
    {
        $query = Product::query()
            ->with(['category', 'images'])
            ->where('is_active', true)
            ->where(function ($q) use ($searchQuery): void {
                $q->where('name', 'like', "%{$searchQuery}%")
                    ->orWhere('description', 'like', "%{$searchQuery}%")
                    ->orWhere('notes', 'like', "%{$searchQuery}%")
                    ->orWhereHas('category', function ($category) use ($searchQuery): void {
                        $category->where('name', 'like', "%{$searchQuery}%");
                    });
            });

        $this->applySorting($query, $filters);

        return $query->paginate($perPage);
    }

    /**
     * Get products filtered by category slug.
     *
     * @param array<string, mixed> $filters
     */
    public function getProductsByCategory(string $categorySlug, array $filters = [], int $perPage = 12): LengthAwarePaginator
    {
        $filters['category'] = $categorySlug;

        return $this->getPaginatedProducts($filters, $perPage);
    }

    /**
     * Create a new product.
     *
     * @param array<string, mixed> $data
     */
    public function createProduct(array $data): Product
    {
        $product = DB::transaction(function () use ($data) {
            $product = Product::create($data);

            if (! empty($data['images'])) {
                $this->attachImages($product, $data['images']);
            }

            if (! empty($data['variants'])) {
                $this->attachVariants($product, $data['variants']);
            }

            return $product->fresh(['category', 'images', 'variants']);
        });

        $this->flushProductCache();

        return $product;
    }

    /**
     * Update an existing product.
     *
     * @param array<string, mixed> $data
     */
    public function updateProduct(Product $product, array $data): Product
    {
        $product = DB::transaction(function () use ($product, $data) {
            $product->update($data);

            if (isset($data['images'])) {
                $this->syncImages($product, $data['images']);
            }

            if (isset($data['variants'])) {
                $this->syncVariants($product, $data['variants']);
            }

            return $product->fresh(['category', 'images', 'variants']);
        });

        $this->flushProductCache();
        $this->forgetProductDetail($product->id, $product->slug);

        return $product;
    }

    /**
     * Delete a product (soft delete).
     */
    public function deleteProduct(Product $product): bool
    {
        $result = $product->delete();

        $this->flushProductCache();
        $this->forgetProductDetail($product->id, $product->slug);

        return $result;
    }

    /**
     * Check if product has sufficient stock.
     */
    public function hasSufficientStock(Product $product, int $quantity, ?int $variantId = null): bool
    {
        if ($variantId !== null) {
            $variant = $product->variants()->find($variantId);

            return $variant !== null && $variant->stock_quantity >= $quantity;
        }

        return $product->stock_quantity >= $quantity;
    }

    /**
     * Decrement product stock.
     */
    public function decrementStock(Product $product, int $quantity, ?int $variantId = null): void
    {
        if ($variantId !== null) {
            $product->variants()
                ->where('id', $variantId)
                ->decrement('stock_quantity', $quantity);
        } else {
            $product->decrement('stock_quantity', $quantity);
        }

        $this->forgetProductDetail($product->id, $product->slug);
    }

    /**
     * Increment product stock.
     */
    public function incrementStock(Product $product, int $quantity, ?int $variantId = null): void
    {
        if ($variantId !== null) {
            $product->variants()
                ->where('id', $variantId)
                ->increment('stock_quantity', $quantity);
        } else {
            $product->increment('stock_quantity', $quantity);
        }

        $this->forgetProductDetail($product->id, $product->slug);
    }

    /**
     * Toggle product featured status.
     */
    public function toggleFeatured(Product $product): Product
    {
        $product->update(['is_featured' => ! $product->is_featured]);

        $this->flushProductCache();
        $this->forgetProductDetail($product->id, $product->slug);

        return $product->fresh();
    }

    /**
     * Toggle product active status.
     */
    public function toggleActive(Product $product): Product
    {
        $product->update(['is_active' => ! $product->is_active]);

        $this->flushProductCache();
        $this->forgetProductDetail($product->id, $product->slug);

        return $product->fresh();
    }

    /**
     * Flush all product-related cache.
     */
    public function flushProductCache(): void
    {
        Cache::tags([self::CACHE_TAG])->flush();
    }

    /**
     * Forget cached product detail by ID and slug.
     */
    public function forgetProductDetail(int $id, string $slug): void
    {
        Cache::tags([self::CACHE_TAG, 'product_detail'])->forget($this->getCacheKey('id', $id));
        Cache::tags([self::CACHE_TAG, 'product_detail'])->forget($this->getCacheKey('slug', $slug));
    }

    /**
     * Generate a cache key for product queries.
     */
    private function getCacheKey(string $type, int|string ...$params): string
    {
        return sprintf('products.%s.%s', $type, implode('.', array_map('strval', $params)));
    }

    /**
     * Apply filters to the product query.
     *
     * @param \Illuminate\Database\Eloquent\Builder<Product> $query
     * @param array<string, mixed> $filters
     */
    private function applyFilters($query, array $filters): void
    {
        if (! empty($filters['search'])) {
            $search = $filters['search'];
            $query->where(function ($q) use ($search): void {
                $q->where('name', 'like', '%'.$search.'%')
                    ->orWhere('description', 'like', '%'.$search.'%')
                    ->orWhere('sku', 'like', '%'.$search.'%');
            });
        }

        if (! empty($filters['category'])) {
            $categoryValue = $filters['category'];
            $query->where('category_id', $categoryValue);
        }

        if (! empty($filters['status'])) {
            if ($filters['status'] === 'active') {
                $query->where('is_active', true);
            } elseif ($filters['status'] === 'inactive') {
                $query->where('is_active', false);
            }
        }

        if (! empty($filters['min_price'])) {
            $query->where('price', '>=', $filters['min_price']);
        }

        if (! empty($filters['max_price'])) {
            $query->where('price', '<=', $filters['max_price']);
        }
    }

    /**
     * Apply sorting to the product query.
     *
     * @param \Illuminate\Database\Eloquent\Builder<Product> $query
     * @param array<string, mixed> $filters
     */
    private function applySorting($query, array $filters): void
    {
        $sortBy = $filters['sort_by'] ?? 'created_at';
        $sortOrder = $filters['sort_order'] ?? 'desc';

        $allowedSortFields = ['name', 'price', 'created_at', 'updated_at'];

        if (in_array($sortBy, $allowedSortFields, true)) {
            $query->orderBy($sortBy, $sortOrder);
        } else {
            $query->orderBy('created_at', 'desc');
        }
    }

    /**
     * Attach images to product.
     *
     * @param array<int, array<string, mixed>> $images
     */
    private function attachImages(Product $product, array $images): void
    {
        foreach ($images as $image) {
            $product->images()->create($image);
        }
    }

    /**
     * Sync product images.
     *
     * @param array<int, array<string, mixed>> $images
     */
    private function syncImages(Product $product, array $images): void
    {
        $product->images()->delete();
        $this->attachImages($product, $images);
    }

    /**
     * Attach variants to product.
     *
     * @param array<int, array<string, mixed>> $variants
     */
    private function attachVariants(Product $product, array $variants): void
    {
        foreach ($variants as $variant) {
            $product->variants()->create($variant);
        }
    }

    /**
     * Sync product variants.
     *
     * @param array<int, array<string, mixed>> $variants
     */
    private function syncVariants(Product $product, array $variants): void
    {
        $existingIds = collect($variants)->pluck('id')->filter()->toArray();
        $product->variants()->whereNotIn('id', $existingIds)->delete();

        foreach ($variants as $variantData) {
            if (isset($variantData['id'])) {
                $product->variants()->where('id', $variantData['id'])->update($variantData);
            } else {
                $product->variants()->create($variantData);
            }
        }
    }
}
