<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\BaseController;
use App\Http\Resources\Api\V1\ProductCollection;
use App\Http\Resources\Api\V1\ProductResource;
use App\Repositories\ProductRepository;
use App\Services\ProductService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProductController extends BaseController
{
    public function __construct(
        private readonly ProductService $productService,
        private readonly ProductRepository $productRepository
    ) {}

    /**
     * Get paginated list of products.
     */
    public function index(Request $request): JsonResponse
    {
        $filters = [
            'category' => $request->get('category'),
            'min_price' => $request->get('min_price'),
            'max_price' => $request->get('max_price'),
            'search' => $request->get('search'),
            'sort_by' => $request->get('sort_by', 'created_at'),
            'sort_order' => $request->get('sort_order', 'desc'),
        ];

        $filters = array_filter($filters, fn ($value) => $value !== null);
        $perPage = (int) $request->get('per_page', config('api.pagination.default_per_page', 15));

        $products = $this->productService->getProducts($filters, $perPage);

        return $this->paginatedResponse(new ProductCollection($products), 'Products retrieved successfully');
    }

    /**
     * Get single product by slug.
     */
    public function show(string $slug): JsonResponse
    {
        $product = $this->productService->getProductBySlug($slug);

        if (! $product) {
            return $this->errorResponse('Product not found', 404);
        }

        return $this->successResponse(new ProductResource($product), 'Product retrieved successfully');
    }

    /**
     * Get featured products.
     */
    public function featured(Request $request): JsonResponse
    {
        $limit = (int) $request->get('limit', config('api.pagination.default_per_page', 15));
        $products = $this->productService->getFeaturedProducts($limit);

        return $this->successResponse(ProductResource::collection($products), 'Featured products retrieved successfully');
    }

    /**
     * Get new arrivals.
     */
    public function newArrivals(Request $request): JsonResponse
    {
        $limit = (int) $request->get('limit', config('api.pagination.default_per_page', 15));
        $products = $this->productService->getNewArrivals($limit);

        return $this->successResponse(ProductResource::collection($products), 'New arrivals retrieved successfully');
    }

    /**
     * Search products.
     */
    public function search(Request $request): JsonResponse
    {
        $query = $request->get('q');

        if (empty($query)) {
            return $this->errorResponse('Search query is required', 422);
        }

        $perPage = (int) $request->get('per_page', config('api.pagination.default_per_page', 15));
        $products = $this->productService->searchProducts($query, $perPage);

        return $this->paginatedResponse(new ProductCollection($products), 'Search results retrieved successfully');
    }
}
