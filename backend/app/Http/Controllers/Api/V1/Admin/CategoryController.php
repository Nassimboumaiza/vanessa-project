<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Api\BaseController;
use App\Http\Requests\Api\V1\StoreCategoryRequest;
use App\Http\Requests\Api\V1\UpdateCategoryRequest;
use App\Http\Resources\Api\V1\CategoryResource;
use App\Models\Category;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class CategoryController extends BaseController
{
    /**
     * Display a listing of categories.
     */
    public function index(Request $request): JsonResponse
    {
        $query = Category::with(['parent', 'children'])
            ->withCount(['products' => function ($q): void {
                $q->where('is_active', true);
            }]);

        if ($request->has('search')) {
            $query->where('name', 'like', '%' . $request->get('search') . '%');
        }

        $categories = $query->orderBy('sort_order')->orderBy('name')->get();

        return $this->successResponse(CategoryResource::collection($categories), 'Categories retrieved successfully');
    }

    /**
     * Store a newly created category.
     */
    public function store(StoreCategoryRequest $request): JsonResponse
    {
        $validated = $request->validated();

        $category = Category::create([
            ...$validated,
            'slug' => Str::slug($validated['name']),
        ]);

        return $this->successResponse(new CategoryResource($category), 'Category created successfully', 201);
    }

    /**
     * Display the specified category.
     */
    public function show(int $id): JsonResponse
    {
        $category = Category::with(['parent', 'children', 'products'])->findOrFail($id);

        return $this->successResponse(new CategoryResource($category), 'Category retrieved successfully');
    }

    /**
     * Update the specified category.
     */
    public function update(UpdateCategoryRequest $request, int $id): JsonResponse
    {
        $category = Category::findOrFail($id);
        $validated = $request->validated();

        $updateData = array_filter($validated, fn ($value) => $value !== null);

        if (isset($updateData['name']) && $updateData['name'] !== $category->name) {
            $updateData['slug'] = Str::slug($updateData['name']);
        }

        $category->update($updateData);

        return $this->successResponse(new CategoryResource($category->fresh()), 'Category updated successfully');
    }

    /**
     * Remove the specified category.
     */
    public function destroy(int $id): JsonResponse
    {
        $category = Category::findOrFail($id);

        if ($category->products()->count() > 0) {
            return $this->errorResponse('Cannot delete category with associated products', 422);
        }

        $category->delete();

        return $this->noContentResponse();
    }
}
