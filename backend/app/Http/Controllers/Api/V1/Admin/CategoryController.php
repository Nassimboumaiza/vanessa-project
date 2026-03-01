<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Api\BaseController;
use App\Http\Requests\Api\V1\StoreCategoryRequest;
use App\Http\Requests\Api\V1\UpdateCategoryRequest;
use App\Http\Resources\Api\V1\CategoryResource;
use App\Services\CategoryService;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class CategoryController extends BaseController
{
    public function __construct(
        private readonly CategoryService $categoryService
    ) {}

    /**
     * Display a listing of categories.
     */
    public function index(Request $request): JsonResponse
    {
        $filters = [
            'search' => $request->get('search'),
            'is_active' => $request->get('is_active'),
        ];

        $filters = array_filter($filters, fn ($value) => $value !== null);

        $categories = $this->categoryService->getPaginatedCategories($filters, 100);

        return $this->successResponse(
            CategoryResource::collection($categories),
            'Categories retrieved successfully'
        );
    }

    /**
     * Store a newly created category.
     */
    public function store(StoreCategoryRequest $request): JsonResponse
    {
        $validated = $request->validated();

        try {
            $category = $this->categoryService->createCategory($validated);

            return $this->successResponse(
                new CategoryResource($category),
                'Category created successfully',
                201
            );
        } catch (\RuntimeException $e) {
            return $this->errorResponse($e->getMessage(), 422);
        }
    }

    /**
     * Display the specified category.
     */
    public function show(int $id): JsonResponse
    {
        try {
            $category = $this->categoryService->findById($id);

            return $this->successResponse(
                new CategoryResource($category->load(['parent', 'children', 'products'])),
                'Category retrieved successfully'
            );
        } catch (ModelNotFoundException $e) {
            return $this->errorResponse('Category not found', 404);
        }
    }

    /**
     * Update the specified category.
     */
    public function update(UpdateCategoryRequest $request, int $id): JsonResponse
    {
        $validated = $request->validated();

        try {
            $category = $this->categoryService->findById($id);
            $updatedCategory = $this->categoryService->updateCategory($category, $validated);

            return $this->successResponse(
                new CategoryResource($updatedCategory),
                'Category updated successfully'
            );
        } catch (ModelNotFoundException $e) {
            return $this->errorResponse('Category not found', 404);
        } catch (\RuntimeException $e) {
            return $this->errorResponse($e->getMessage(), 422);
        }
    }

    /**
     * Remove the specified category.
     */
    public function destroy(int $id): Response
    {
        try {
            $category = $this->categoryService->findById($id);
            $this->categoryService->deleteCategory($category);

            return $this->noContentResponse();
        } catch (ModelNotFoundException $e) {
            return $this->errorResponse('Category not found', 404);
        } catch (\RuntimeException $e) {
            return $this->errorResponse($e->getMessage(), 422);
        }
    }

    /**
     * Toggle category active status.
     */
    public function toggleActive(int $id): JsonResponse
    {
        try {
            $category = $this->categoryService->findById($id);
            $updatedCategory = $this->categoryService->toggleActive($category);

            return $this->successResponse(
                new CategoryResource($updatedCategory),
                'Category status updated successfully'
            );
        } catch (ModelNotFoundException $e) {
            return $this->errorResponse('Category not found', 404);
        }
    }
}
