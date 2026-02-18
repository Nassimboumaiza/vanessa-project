<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\BaseController;
use App\Http\Requests\Api\V1\WishlistItemRequest;
use App\Http\Resources\Api\V1\WishlistResource;
use App\Models\Product;
use App\Models\Wishlist;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class WishlistController extends BaseController
{
    /**
     * Get user wishlist.
     */
    public function index(Request $request): JsonResponse
    {
        $wishlist = Wishlist::where('user_id', $request->user()->id)
            ->with(['product.images', 'variant'])
            ->orderBy('created_at', 'desc')
            ->get();

        return $this->successResponse(WishlistResource::collection($wishlist), 'Wishlist retrieved successfully');
    }

    /**
     * Add item to wishlist.
     */
    public function addItem(WishlistItemRequest $request): JsonResponse
    {
        $validated = $request->validated();

        $product = Product::findOrFail($validated['product_id']);

        if (! $product->is_active) {
            return $this->errorResponse('Product is not available', 400);
        }

        if ($validated['variant_id']) {
            $variant = $product->variants()->where('id', $validated['variant_id'])->where('is_active', true)->first();
            if (! $variant) {
                return $this->errorResponse('Invalid product variant', 422);
            }
        }

        $existingItem = Wishlist::where('user_id', $request->user()->id)
            ->where('product_id', $validated['product_id'])
            ->where('variant_id', $validated['variant_id'])
            ->first();

        if ($existingItem) {
            return $this->errorResponse('Item already in wishlist', 422);
        }

        $wishlistItem = Wishlist::create([
            'user_id' => $request->user()->id,
            'product_id' => $validated['product_id'],
            'variant_id' => $validated['variant_id'],
        ]);

        $wishlistItem->load(['product.images', 'variant']);

        return $this->successResponse(new WishlistResource($wishlistItem), 'Item added to wishlist', 201);
    }

    /**
     * Remove item from wishlist.
     */
    public function removeItem(Request $request, int $id): JsonResponse
    {
        $item = Wishlist::where('user_id', $request->user()->id)
            ->where('id', $id)
            ->first();

        if (! $item) {
            return $this->errorResponse('Wishlist item not found', 404);
        }

        $item->delete();

        return $this->successResponse([], 'Item removed from wishlist');
    }
}
