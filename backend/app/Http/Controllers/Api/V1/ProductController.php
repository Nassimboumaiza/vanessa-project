<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\BaseController;
use App\Http\Requests\Api\V1\ProductSearchRequest;
use App\Http\Resources\Api\V1\ProductCollection;
use App\Http\Resources\Api\V1\ProductResource;
use App\Services\ProductService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProductController extends BaseController
{
    public function __construct(
        private readonly ProductService $productService
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
        $perPage = min((int) $request->get('per_page', config('api.pagination.default_per_page', 15)), 100);

        $products = $this->productService->getPaginatedProducts($filters, $perPage);

        return $this->paginatedResponse(new ProductCollection($products), 'Products retrieved successfully');
    }

    /**
     * Get single product by slug.
     */
    public function show(string $slug): JsonResponse
    {
        try {
            $product = $this->productService->findBySlug($slug);

            return $this->successResponse(new ProductResource($product), 'Product retrieved successfully');
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return $this->errorResponse('Product not found', 404);
        }
    }

    /**
     * Get featured products.
     */
    public function featured(Request $request): JsonResponse
    {
        $limit = (int) $request->get('limit', 8);
        $products = $this->productService->getFeaturedProducts($limit);

        return $this->successResponse(ProductResource::collection($products), 'Featured products retrieved successfully');
    }

    /**
     * Get new arrivals.
     */
    public function newArrivals(Request $request): JsonResponse
    {
        $limit = (int) $request->get('limit', 8);
        $products = $this->productService->getNewArrivals($limit);

        return $this->successResponse(ProductResource::collection($products), 'New arrivals retrieved successfully');
    }

    /**
     * Search products.
     */
    public function search(ProductSearchRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $query = $validated['q'];

        $filters = [
            'sort_by' => $validated['sort_by'] ?? 'created_at',
            'sort_order' => $validated['sort_order'] ?? 'desc',
            'category' => $validated['category'] ?? null,
            'min_price' => $validated['min_price'] ?? null,
            'max_price' => $validated['max_price'] ?? null,
        ];

        $perPage = $validated['per_page'] ?? config('api.pagination.default_per_page', 15);
        $products = $this->productService->searchProducts($query, $filters, $perPage);

        return $this->paginatedResponse(new ProductCollection($products), 'Search results retrieved successfully');
    }

    /**
     * Get related products.
     */
    public function related(string $slug, Request $request): JsonResponse
    {
        try {
            $product = $this->productService->findBySlug($slug);
            $limit = (int) $request->get('limit', 4);
            $relatedProducts = $this->productService->getRelatedProducts($product, $limit);

            return $this->successResponse(
                ProductResource::collection($relatedProducts),
                'Related products retrieved successfully'
            );
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return $this->errorResponse('Product not found', 404);
        }
    }
}
