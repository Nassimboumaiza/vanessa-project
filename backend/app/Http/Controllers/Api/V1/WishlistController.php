<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\BaseController;
use App\Http\Requests\Api\V1\WishlistItemRequest;
use App\Http\Resources\Api\V1\WishlistResource;
use App\Services\ProductService;
use App\Services\WishlistService;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class WishlistController extends BaseController
{
    public function __construct(
        private readonly WishlistService $wishlistService,
        private readonly ProductService $productService
    ) {}

    /**
     * Get user wishlist.
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $wishlist = $this->wishlistService->getWishlistWithItems($user);

        return $this->successResponse(
            WishlistResource::collection($wishlist->items),
            'Wishlist retrieved successfully'
        );
    }

    /**
     * Add item to wishlist.
     */
    public function addItem(WishlistItemRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $user = $request->user();

        try {
            $product = $this->productService->findById($validated['product_id']);

            if (! $product->is_active) {
                return $this->errorResponse('Product is not available', 400);
            }

            $item = $this->wishlistService->addItem($user, $product);

            return $this->successResponse(
                new WishlistResource($item),
                'Item added to wishlist',
                201
            );
        } catch (ModelNotFoundException $e) {
            return $this->errorResponse('Product not found', 404);
        } catch (\RuntimeException $e) {
            return $this->errorResponse($e->getMessage(), 422);
        }
    }

    /**
     * Remove item from wishlist.
     */
    public function removeItem(Request $request, int $id): JsonResponse
    {
        try {
            $item = $this->wishlistService->findItemById($id);
            $user = $request->user();

            // Ensure user owns the wishlist item
            if ($item->wishlist->user_id !== $user->id) {
                return $this->errorResponse('Wishlist item not found', 404);
            }

            $this->wishlistService->removeItem($item);

            return $this->successResponse([], 'Item removed from wishlist');
        } catch (ModelNotFoundException $e) {
            return $this->errorResponse('Wishlist item not found', 404);
        }
    }
}
