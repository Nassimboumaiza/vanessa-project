<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Api\BaseController;
use App\Http\Requests\Api\V1\StoreProductRequest;
use App\Http\Requests\Api\V1\UpdateProductRequest;
use App\Http\Resources\Api\V1\ProductCollection;
use App\Http\Resources\Api\V1\ProductImageResource;
use App\Http\Resources\Api\V1\ProductResource;
use App\Models\Product;
use App\Models\ProductImage;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ProductController extends BaseController
{
    /**
     * Display a listing of products.
     */
    public function index(Request $request): JsonResponse
    {
        $query = Product::with(['category', 'images', 'variants']);

        if ($request->has('search')) {
            $search = $request->get('search');
            $query->where(function ($q) use ($search): void {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('sku', 'like', "%{$search}%");
            });
        }

        if ($request->has('category')) {
            $query->where('category_id', $request->get('category'));
        }

        if ($request->has('status')) {
            $isActive = $request->get('status') === 'active';
            $query->where('is_active', $isActive);
        }

        $perPage = (int) $request->get('per_page', config('api.pagination.default_per_page', 15));
        $products = $query->orderBy('created_at', 'desc')->paginate($perPage);

        return $this->paginatedResponse(new ProductCollection($products), 'Products retrieved successfully');
    }

    /**
     * Store a newly created product.
     */
    public function store(StoreProductRequest $request): JsonResponse
    {
        $validated = $request->validated();

        DB::beginTransaction();

        try {
            $product = Product::create([
                ...$validated,
                'slug' => Str::slug($validated['name']),
            ]);

            if (! empty($validated['variants'])) {
                foreach ($validated['variants'] as $variant) {
                    $product->variants()->create($variant);
                }
            }

            DB::commit();

            return $this->successResponse(new ProductResource($product->fresh()->load(['category', 'images', 'variants'])), 'Product created successfully', 201);
        } catch (\Exception $e) {
            DB::rollBack();

            return $this->errorResponse('Failed to create product: '.$e->getMessage(), 500);
        }
    }

    /**
     * Display the specified product.
     */
    public function show(int $id): JsonResponse
    {
        $product = Product::with(['category', 'images', 'variants', 'reviews.user'])->findOrFail($id);

        return $this->successResponse(new ProductResource($product), 'Product retrieved successfully');
    }

    /**
     * Update the specified product.
     */
    public function update(UpdateProductRequest $request, int $id): JsonResponse
    {
        $product = Product::findOrFail($id);
        $validated = $request->validated();

        DB::beginTransaction();

        try {
            $updateData = $validated;
            if (isset($validated['name']) && $validated['name'] !== $product->name) {
                $updateData['slug'] = Str::slug($validated['name']);
            }

            $product->update($updateData);

            DB::commit();

            return $this->successResponse(new ProductResource($product->fresh()->load(['category', 'images', 'variants'])), 'Product updated successfully');
        } catch (\Exception $e) {
            DB::rollBack();

            return $this->errorResponse('Failed to update product: '.$e->getMessage(), 500);
        }
    }

    /**
     * Remove the specified product.
     */
    public function destroy(int $id): Response
    {
        $product = Product::findOrFail($id);
        $product->delete();

        return $this->noContentResponse();
    }

    /**
     * Upload product images.
     */
    public function uploadImages(Request $request, int $id): JsonResponse
    {
        $product = Product::findOrFail($id);

        $validated = $request->validate([
            'images' => ['required', 'array'],
            'images.*' => ['required', 'image', 'mimes:jpeg,png,jpg,webp', 'max:5120'],
        ]);

        $uploadedImages = [];

        foreach ($request->file('images') as $index => $image) {
            $path = $image->store('products/'.$product->id, 'public');

            $productImage = ProductImage::create([
                'product_id' => $product->id,
                'image_path' => $path,
                'alt_text' => $product->name,
                'sort_order' => $index,
                'is_primary' => $index === 0 && ! $product->images()->exists(),
            ]);

            $uploadedImages[] = $productImage;
        }

        return $this->successResponse(ProductImageResource::collection($product->images()->get()), 'Images uploaded successfully', 201);
    }

    /**
     * Delete product image.
     */
    public function deleteImage(int $id, int $imageId): JsonResponse
    {
        $product = Product::findOrFail($id);
        $image = $product->images()->where('id', $imageId)->firstOrFail();

        // TODO: Delete file from storage
        // Storage::disk('public')->delete($image->image_path);

        $image->delete();

        return $this->successResponse([], 'Image deleted successfully');
    }
}
