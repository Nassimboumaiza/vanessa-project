<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\BaseController;
use App\Http\Resources\Api\V1\CategoryResource;
use App\Http\Resources\Api\V1\ProductCollection;
use App\Services\CategoryService;
use App\Services\ProductService;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CategoryController extends BaseController
{
    public function __construct(
        private readonly CategoryService $categoryService,
        private readonly ProductService $productService
    ) {}

    /**
     * Get all active categories.
     */
    public function index(): JsonResponse
    {
        $categories = $this->categoryService->getActiveCategories();

        return $this->successResponse(CategoryResource::collection($categories), 'Categories retrieved successfully');
    }

    /**
     * Get category by slug.
     */
    public function show(string $slug): JsonResponse
    {
        try {
            $category = $this->categoryService->findBySlug($slug);

            return $this->successResponse(new CategoryResource($category), 'Category retrieved successfully');
        } catch (ModelNotFoundException $e) {
            return $this->errorResponse('Category not found', 404);
        }
    }

    /**
     * Get products by category.
     */
    public function products(string $slug, Request $request): JsonResponse
    {
        try {
            $category = $this->categoryService->findBySlug($slug);

            $filters = [
                'min_price' => $request->get('min_price'),
                'max_price' => $request->get('max_price'),
                'sort_by' => $request->get('sort_by', 'created_at'),
                'sort_order' => $request->get('sort_order', 'desc'),
            ];

            $filters = array_filter($filters, fn ($value) => $value !== null);
            $perPage = (int) $request->get('per_page', config('api.pagination.default_per_page', 15));

            $products = $this->categoryService->getCategoryProducts($category, $filters, $perPage);

            return $this->paginatedResponse(new ProductCollection($products), 'Category products retrieved successfully');
        } catch (ModelNotFoundException $e) {
            return $this->errorResponse('Category not found', 404);
        }
    }
}
