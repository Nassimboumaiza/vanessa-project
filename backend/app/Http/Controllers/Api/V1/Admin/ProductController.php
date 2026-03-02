<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Api\BaseController;
use App\Http\Requests\Api\V1\ProductImageRequest;
use App\Http\Requests\Api\V1\StoreProductRequest;
use App\Http\Requests\Api\V1\UpdateProductRequest;
use App\Http\Resources\Api\V1\ProductCollection;
use App\Http\Resources\Api\V1\ProductImageResource;
use App\Http\Resources\Api\V1\ProductResource;
use App\Services\ProductService;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class ProductController extends BaseController
{
    public function __construct(
        private readonly ProductService $productService
    ) {}

    /**
     * Display a listing of products.
     */
    public function index(Request $request): JsonResponse
    {
        $filters = [
            'search' => $request->get('search'),
            'category' => $request->get('category'),
            'status' => $request->get('status'),
            'sort_by' => $request->get('sort_by', 'created_at'),
            'sort_order' => $request->get('sort_order', 'desc'),
        ];

        $filters = array_filter($filters, fn ($value) => $value !== null);
        $perPage = (int) $request->get('per_page', config('api.pagination.default_per_page', 15));

        // Admin can see all products including inactive ones
        $products = $this->productService->getPaginatedProducts($filters, $perPage, activeOnly: false);

        return $this->paginatedResponse(new ProductCollection($products), 'Products retrieved successfully');
    }

    /**
     * Store a newly created product.
     */
    public function store(StoreProductRequest $request): JsonResponse
    {
        $validated = $request->validated();

        try {
            $product = $this->productService->createProduct($validated);

            return $this->successResponse(
                new ProductResource($product),
                'Product created successfully',
                201
            );
        } catch (\Exception $e) {
            return $this->errorResponse('Failed to create product: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Display the specified product.
     */
    public function show(int $id): JsonResponse
    {
        try {
            $product = $this->productService->findById($id);

            return $this->successResponse(new ProductResource($product), 'Product retrieved successfully');
        } catch (ModelNotFoundException $e) {
            return $this->errorResponse('Product not found', 404);
        }
    }

    /**
     * Update the specified product.
     */
    public function update(UpdateProductRequest $request, int $id): JsonResponse
    {
        $validated = $request->validated();

        try {
            $product = $this->productService->findById($id);
            $updatedProduct = $this->productService->updateProduct($product, $validated);

            return $this->successResponse(
                new ProductResource($updatedProduct),
                'Product updated successfully'
            );
        } catch (ModelNotFoundException $e) {
            return $this->errorResponse('Product not found', 404);
        } catch (\Exception $e) {
            return $this->errorResponse('Failed to update product: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Remove the specified product.
     */
    public function destroy(int $id): JsonResponse
    {
        try {
            $product = $this->productService->findById($id);
            $this->productService->deleteProduct($product);

            return response()->json([
                'success' => true,
                'message' => 'Product deleted successfully',
            ], 204);
        } catch (ModelNotFoundException $e) {
            return $this->errorResponse('Product not found', 404);
        }
    }

    /**
     * Toggle product featured status.
     */
    public function toggleFeatured(int $id): JsonResponse
    {
        try {
            $product = $this->productService->findById($id);
            $updatedProduct = $this->productService->toggleFeatured($product);

            return $this->successResponse(
                new ProductResource($updatedProduct),
                'Product featured status updated'
            );
        } catch (ModelNotFoundException $e) {
            return $this->errorResponse('Product not found', 404);
        }
    }

    /**
     * Toggle product active status.
     */
    public function toggleActive(int $id): JsonResponse
    {
        try {
            $product = $this->productService->findById($id);
            $updatedProduct = $this->productService->toggleActive($product);

            return $this->successResponse(
                new ProductResource($updatedProduct),
                'Product active status updated'
            );
        } catch (ModelNotFoundException $e) {
            return $this->errorResponse('Product not found', 404);
        }
    }

    /**
     * Upload product images.
     */
    public function uploadImages(ProductImageRequest $request, int $id): JsonResponse
    {
        try {
            $product = $this->productService->findById($id);
            $uploadedImages = [];

            foreach ($request->file('images') as $index => $image) {
                // Generate safe filename
                $extension = $image->getClientOriginalExtension();
                $safeFilename = sprintf(
                    '%s_%d_%d.%s',
                    \Str::slug($product->name),
                    $product->id,
                    $index + 1,
                    $extension
                );

                $path = $image->storeAs(
                    'products/' . $product->id,
                    $safeFilename,
                    'public'
                );

                $productImage = $product->images()->create([
                    'image_path' => $path,
                    'alt_text' => $product->name,
                    'sort_order' => $index,
                    'is_primary' => $index === 0 && ! $product->images()->exists(),
                ]);
                $uploadedImages[] = $productImage;
            }

            return $this->successResponse(
                ProductImageResource::collection($product->images()->get()),
                'Images uploaded successfully',
                201
            );
        } catch (ModelNotFoundException $e) {
            return $this->errorResponse('Product not found', 404);
        }
    }

    /**
     * Delete product image.
     */
    public function deleteImage(int $id, int $imageId): JsonResponse
    {
        try {
            $product = $this->productService->findById($id);
            $image = $product->images()->where('id', $imageId)->firstOrFail();

            // TODO: Delete file from storage
            // Storage::disk('public')->delete($image->image_path);

            $image->delete();

            return $this->successResponse([], 'Image deleted successfully');
        } catch (ModelNotFoundException $e) {
            return $this->errorResponse('Product or image not found', 404);
        }
    }
}
