<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\BaseController;
use App\Http\Resources\Api\V1\CategoryCollection;
use App\Http\Resources\Api\V1\CategoryResource;
use App\Http\Resources\Api\V1\ProductCollection;
use App\Models\Category;
use App\Repositories\ProductRepository;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CategoryController extends BaseController
{
    public function __construct(
        private readonly ProductRepository $productRepository
    ) {
    }

    /**
     * Get all active categories.
     */
    public function index(): JsonResponse
    {
        $categories = Category::query()
            ->where('is_active', true)
            ->withCount(['products' => function ($query): void {
                $query->where('is_active', true);
            }])
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();

        return $this->successResponse(CategoryResource::collection($categories), 'Categories retrieved successfully');
    }

    /**
     * Get category by slug.
     */
    public function show(string $slug): JsonResponse
    {
        $category = Category::query()
            ->where('slug', $slug)
            ->where('is_active', true)
            ->withCount(['products' => function ($query): void {
                $query->where('is_active', true);
            }])
            ->first();

        if (! $category) {
            return $this->errorResponse('Category not found', 404);
        }

        return $this->successResponse(new CategoryResource($category), 'Category retrieved successfully');
    }

    /**
     * Get products by category.
     */
    public function products(string $slug, Request $request): JsonResponse
    {
        $category = Category::query()
            ->where('slug', $slug)
            ->where('is_active', true)
            ->first();

        if (! $category) {
            return $this->errorResponse('Category not found', 404);
        }

        $filters = [
            'min_price' => $request->get('min_price'),
            'max_price' => $request->get('max_price'),
            'sort_by' => $request->get('sort_by', 'created_at'),
            'sort_order' => $request->get('sort_order', 'desc'),
        ];

        $filters = array_filter($filters, fn ($value) => $value !== null);
        $perPage = (int) $request->get('per_page', config('api.pagination.default_per_page'));

        $products = $this->productRepository->getByCategory($slug, $filters, $perPage);

        return $this->paginatedResponse(new ProductCollection($products), 'Category products retrieved successfully');
    }
}
