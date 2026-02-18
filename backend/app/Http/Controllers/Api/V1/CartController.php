<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\BaseController;
use App\Http\Requests\Api\V1\CartItemRequest;
use App\Http\Requests\Api\V1\UpdateCartItemRequest;
use App\Http\Resources\Api\V1\CartResource;
use App\Models\Cart;
use App\Models\Product;
use App\Models\ProductVariant;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CartController extends BaseController
{
    /**
     * Get or create cart.
     */
    public function index(Request $request): JsonResponse
    {
        $cart = $this->getOrCreateCart($request);
        $cart->load(['items.product.images', 'items.variant']);

        return $this->successResponse(new CartResource($cart), 'Cart retrieved successfully');
    }

    /**
     * Add item to cart.
     */
    public function addItem(CartItemRequest $request): JsonResponse
    {
        $validated = $request->validated();

        $cart = $this->getOrCreateCart($request);

        $product = Product::findOrFail($validated['product_id']);

        if (! $product->is_active) {
            return $this->errorResponse('Product is not available', 400);
        }

        $price = $product->price;
        $variant = null;

        if ($validated['variant_id']) {
            $variant = ProductVariant::where('id', $validated['variant_id'])
                ->where('product_id', $product->id)
                ->where('is_active', true)
                ->first();

            if (! $variant) {
                return $this->errorResponse('Invalid product variant', 422);
            }

            $price = $variant->price;

            if ($variant->stock_quantity < $validated['quantity']) {
                return $this->errorResponse('Insufficient stock for this variant', 400);
            }
        } else {
            if ($product->stock_quantity < $validated['quantity']) {
                return $this->errorResponse('Insufficient stock', 400);
            }
        }

        $existingItem = $cart->items()
            ->where('product_id', $validated['product_id'])
            ->where('variant_id', $validated['variant_id'])
            ->first();

        if ($existingItem) {
            $newQuantity = $existingItem->quantity + $validated['quantity'];

            if ($variant && $variant->stock_quantity < $newQuantity) {
                return $this->errorResponse('Insufficient stock for this variant', 400);
            } elseif (! $variant && $product->stock_quantity < $newQuantity) {
                return $this->errorResponse('Insufficient stock', 400);
            }

            $existingItem->update([
                'quantity' => $newQuantity,
                'total_price' => $price * $newQuantity,
            ]);
        } else {
            $cart->items()->create([
                'product_id' => $validated['product_id'],
                'variant_id' => $validated['variant_id'],
                'quantity' => $validated['quantity'],
                'unit_price' => $price,
                'total_price' => $price * $validated['quantity'],
            ]);
        }

        $this->updateCartTotals($cart);
        $cart->load(['items.product.images', 'items.variant']);

        return $this->successResponse(new CartResource($cart), 'Item added to cart', 201);
    }

    /**
     * Update cart item quantity.
     */
    public function updateItem(UpdateCartItemRequest $request, int $id): JsonResponse
    {
        $validated = $request->validated();

        $cart = $this->getOrCreateCart($request);
        $item = $cart->items()->where('id', $id)->first();

        if (! $item) {
            return $this->errorResponse('Cart item not found', 404);
        }

        if ($validated['quantity'] === 0) {
            $item->delete();
        } else {
            $product = $item->product;
            $variant = $item->variant;

            if ($variant && $variant->stock_quantity < $validated['quantity']) {
                return $this->errorResponse('Insufficient stock for this variant', 400);
            } elseif (! $variant && $product->stock_quantity < $validated['quantity']) {
                return $this->errorResponse('Insufficient stock', 400);
            }

            $item->update([
                'quantity' => $validated['quantity'],
                'total_price' => $item->unit_price * $validated['quantity'],
            ]);
        }

        $this->updateCartTotals($cart);
        $cart->load(['items.product.images', 'items.variant']);

        return $this->successResponse(new CartResource($cart), 'Cart item updated');
    }

    /**
     * Remove item from cart.
     */
    public function removeItem(Request $request, int $id): JsonResponse
    {
        $cart = $this->getOrCreateCart($request);
        $item = $cart->items()->where('id', $id)->first();

        if (! $item) {
            return $this->errorResponse('Cart item not found', 404);
        }

        $item->delete();
        $this->updateCartTotals($cart);
        $cart->load(['items.product.images', 'items.variant']);

        return $this->successResponse(new CartResource($cart), 'Item removed from cart');
    }

    /**
     * Clear cart.
     */
    public function clear(Request $request): JsonResponse
    {
        $cart = $this->getOrCreateCart($request);
        $cart->items()->delete();
        $this->updateCartTotals($cart);

        return $this->successResponse(new CartResource($cart->fresh()), 'Cart cleared');
    }

    /**
     * Get or create cart for user or session.
     */
    private function getOrCreateCart(Request $request): Cart
    {
        if ($request->user()) {
            return Cart::firstOrCreate(
                ['user_id' => $request->user()->id],
                ['total_amount' => 0, 'total_items' => 0]
            );
        }

        $sessionId = $request->session()->getId();

        return Cart::firstOrCreate(
            ['session_id' => $sessionId],
            ['total_amount' => 0, 'total_items' => 0]
        );
    }

    /**
     * Update cart totals.
     */
    private function updateCartTotals(Cart $cart): void
    {
        $totals = $cart->items()->selectRaw('
            SUM(total_price) as total_amount,
            SUM(quantity) as total_items
        ')->first();

        $cart->update([
            'total_amount' => $totals->total_amount ?? 0,
            'total_items' => $totals->total_items ?? 0,
        ]);
    }
}
