<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Category;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class CategoryService
{
    /**
     * Get all active categories.
     */
    public function getActiveCategories(): Collection
    {
        return Category::query()
            ->where('is_active', true)
            ->orderBy('display_order')
            ->orderBy('name')
            ->get();
    }

    /**
     * Get category by slug.
     *
     * @throws ModelNotFoundException
     */
    public function findBySlug(string $slug): Category
    {
        return Category::query()
            ->where('slug', $slug)
            ->where('is_active', true)
            ->firstOrFail();
    }

    /**
     * Get category by ID.
     *
     * @throws ModelNotFoundException
     */
    public function findById(int $id): Category
    {
        return Category::query()->findOrFail($id);
    }

    /**
     * Get paginated categories with filters.
     *
     * @param array<string, mixed> $filters
     */
    public function getPaginatedCategories(array $filters = [], int $perPage = 15): LengthAwarePaginator
    {
        $query = Category::query()->withCount('products');

        if (! empty($filters['search'])) {
            $query->where(function ($q) use ($filters): void {
                $q->where('name', 'like', "%{$filters['search']}%")
                    ->orWhere('description', 'like', "%{$filters['search']}%");
            });
        }

        if (isset($filters['is_active'])) {
            $query->where('is_active', $filters['is_active']);
        }

        $sortBy = $filters['sort_by'] ?? 'display_order';
        $sortOrder = $filters['sort_order'] ?? 'asc';

        $query->orderBy($sortBy, $sortOrder);

        return $query->paginate($perPage);
    }

    /**
     * Get products for a specific category.
     *
     * @param array<string, mixed> $filters
     */
    public function getCategoryProducts(Category $category, array $filters = [], int $perPage = 12): LengthAwarePaginator
    {
        $query = $category->products()
            ->with(['images', 'reviews'])
            ->where('is_active', true);

        if (! empty($filters['min_price'])) {
            $query->where('price', '>=', $filters['min_price']);
        }

        if (! empty($filters['max_price'])) {
            $query->where('price', '<=', $filters['max_price']);
        }

        $sortBy = $filters['sort_by'] ?? 'created_at';
        $sortOrder = $filters['sort_order'] ?? 'desc';
        $query->orderBy($sortBy, $sortOrder);

        return $query->paginate($perPage);
    }

    /**
     * Create a new category.
     *
     * @param array<string, mixed> $data
     */
    public function createCategory(array $data): Category
    {
        return DB::transaction(function () use ($data) {
            if (empty($data['slug'])) {
                $data['slug'] = Str::slug($data['name']);
            }

            return Category::create($data);
        });
    }

    /**
     * Update an existing category.
     *
     * @param array<string, mixed> $data
     */
    public function updateCategory(Category $category, array $data): Category
    {
        return DB::transaction(function () use ($category, $data) {
            if (isset($data['name']) && empty($data['slug'])) {
                $data['slug'] = Str::slug($data['name']);
            }

            $category->update($data);

            return $category->fresh();
        });
    }

    /**
     * Delete a category.
     */
    public function deleteCategory(Category $category): bool
    {
        return DB::transaction(function () use ($category) {
            // Reassign products to null category or prevent deletion if products exist
            if ($category->products()->count() > 0) {
                throw new \RuntimeException('Cannot delete category with associated products');
            }

            return $category->delete();
        });
    }

    /**
     * Toggle category active status.
     */
    public function toggleActive(Category $category): Category
    {
        $category->update(['is_active' => ! $category->is_active]);

        return $category->fresh();
    }

    /**
     * Get category tree structure.
     */
    public function getCategoryTree(): Collection
    {
        return Category::query()
            ->where('is_active', true)
            ->whereNull('parent_id')
            ->with(['children' => function ($query): void {
                $query->where('is_active', true)->orderBy('display_order');
            }])
            ->orderBy('display_order')
            ->get();
    }
}
