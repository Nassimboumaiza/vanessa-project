<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Product;
use App\Repositories\ProductRepository;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

class ProductService
{
    public function __construct(
        private readonly ProductRepository $productRepository
    ) {
    }

    /**
     * Get paginated products with filters.
     */
    public function getProducts(array $filters = [], int $perPage = 12): LengthAwarePaginator
    {
        return $this->productRepository->getPaginated($filters, $perPage);
    }

    /**
     * Get featured products.
     */
    public function getFeaturedProducts(int $limit = 8): Collection
    {
        return $this->productRepository->getFeatured($limit);
    }

    /**
     * Get new arrivals.
     */
    public function getNewArrivals(int $limit = 8): Collection
    {
        return $this->productRepository->getNewArrivals($limit);
    }

    /**
     * Get product by slug.
     */
    public function getProductBySlug(string $slug): ?Product
    {
        return $this->productRepository->findBySlug($slug);
    }

    /**
     * Get related products.
     */
    public function getRelatedProducts(Product $product, int $limit = 4): Collection
    {
        return $this->productRepository->getRelated($product, $limit);
    }

    /**
     * Search products.
     */
    public function searchProducts(string $query, int $perPage = 12): LengthAwarePaginator
    {
        return $this->productRepository->search($query, $perPage);
    }

    /**
     * Get products by category.
     */
    public function getProductsByCategory(string $categorySlug, array $filters = [], int $perPage = 12): LengthAwarePaginator
    {
        return $this->productRepository->getByCategory($categorySlug, $filters, $perPage);
    }
}
