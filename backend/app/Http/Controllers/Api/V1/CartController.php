<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\BaseController;
use App\Http\Requests\Api\V1\BatchCartItemRequest;
use App\Http\Requests\Api\V1\CartItemRequest;
use App\Http\Requests\Api\V1\UpdateCartItemRequest;
use App\Http\Resources\Api\V1\CartResource;
use App\Http\Resources\Api\V1\CartTotalsResource;
use App\Models\CartItem;
use App\Services\CartService;
use App\Services\StockReservationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CartController extends BaseController
{
    public function __construct(
        private readonly CartService $cartService,
        private readonly StockReservationService $stockReservationService
    ) {}

    /**
     * Get or create cart.
     */
    public function index(Request $request): JsonResponse
    {
        $sessionId = $request->hasSession() ? $request->session()->getId() : null;
        $cart = $this->cartService->getOrCreateCart(
            $request->user(),
            $sessionId
        );

        $cart = $this->cartService->getCartWithItems($cart);

        return $this->successResponse(new CartResource($cart), 'Cart retrieved successfully');
    }

    /**
     * Add item to cart.
     */
    public function addItem(CartItemRequest $request): JsonResponse
    {
        $validated = $request->validated();

        $sessionId = $request->hasSession() ? $request->session()->getId() : null;
        $cart = $this->cartService->getOrCreateCart(
            $request->user(),
            $sessionId
        );

        try {
            $item = $this->cartService->addItem($cart, [
                'product_id' => $validated['product_id'],
                'variant_id' => $validated['variant_id'] ?? null,
                'quantity' => $validated['quantity'],
            ]);

            $cart = $this->cartService->getCartWithItems($cart);

            return $this->successResponse(new CartResource($cart), 'Item added to cart', 201);
        } catch (\RuntimeException $e) {
            return $this->errorResponse($e->getMessage(), 400);
        }
    }

    /**
     * Batch sync items to cart.
     */
    public function batchSyncItems(BatchCartItemRequest $request): JsonResponse
    {
        $validated = $request->validated();

        $sessionId = $request->hasSession() ? $request->session()->getId() : null;
        $cart = $this->cartService->getOrCreateCart(
            $request->user(),
            $sessionId
        );

        $items = $this->cartService->batchSyncItems($cart, $validated['items']);

        // Reserve stock for authenticated users
        $reservationResult = null;
        if ($request->user() && $items->isNotEmpty()) {
            $reservationItems = $items->map(function ($item) {
                return [
                    'product_id' => $item->product_id,
                    'variant_id' => $item->product_variant_id,
                    'quantity' => $item->quantity,
                ];
            })->toArray();

            $reservationResult = $this->stockReservationService->reserveStock(
                $request->user()->id,
                $reservationItems,
                30
            );
        }

        $cart = $this->cartService->getCartWithItems($cart);
        $totals = $this->cartService->calculateTotals($cart);

        $meta = [];
        if ($reservationResult) {
            $meta['stock_reservation'] = $reservationResult;
        }

        return $this->successResponse(
            new CartResource($cart),
            count($validated['items']) . ' item(s) synced to cart',
            200,
            $meta
        );
    }

    /**
     * Update cart item quantity.
     */
    public function updateItem(UpdateCartItemRequest $request, int $id): JsonResponse
    {
        $validated = $request->validated();

        $sessionId = $request->hasSession() ? $request->session()->getId() : null;
        $cart = $this->cartService->getOrCreateCart(
            $request->user(),
            $sessionId
        );

        $item = CartItem::query()
            ->where('id', $id)
            ->whereHas('cart', function ($query) use ($cart): void {
                $query->where('id', $cart->id);
            })
            ->first();

        if (! $item) {
            return $this->errorResponse('Cart item not found', 404);
        }

        try {
            $this->cartService->updateItemQuantity($item, $validated['quantity']);

            $cart = $this->cartService->getCartWithItems($cart);

            return $this->successResponse(new CartResource($cart), 'Cart item updated');
        } catch (\RuntimeException $e) {
            return $this->errorResponse($e->getMessage(), 400);
        }
    }

    /**
     * Remove item from cart.
     */
    public function removeItem(Request $request, int $id): JsonResponse
    {
        $sessionId = $request->hasSession() ? $request->session()->getId() : null;
        $cart = $this->cartService->getOrCreateCart(
            $request->user(),
            $sessionId
        );

        $item = CartItem::query()
            ->where('id', $id)
            ->whereHas('cart', function ($query) use ($cart): void {
                $query->where('id', $cart->id);
            })
            ->first();

        if (! $item) {
            return $this->errorResponse('Cart item not found', 404);
        }

        $this->cartService->removeItem($item);

        $cart = $this->cartService->getCartWithItems($cart);

        return $this->successResponse(new CartResource($cart), 'Item removed from cart');
    }

    /**
     * Clear cart.
     */
    public function clear(Request $request): JsonResponse
    {
        $sessionId = $request->hasSession() ? $request->session()->getId() : null;
        $cart = $this->cartService->getOrCreateCart(
            $request->user(),
            $sessionId
        );

        // Release stock reservations for authenticated users
        if ($request->user()) {
            $this->stockReservationService->releaseUserReservations($request->user()->id);
        }

        $this->cartService->clearCart($cart);

        return $this->successResponse(new CartResource($cart->fresh()), 'Cart cleared');
    }

    /**
     * Get cart totals.
     */
    public function totals(Request $request): JsonResponse
    {
        $sessionId = $request->hasSession() ? $request->session()->getId() : null;
        $cart = $this->cartService->getOrCreateCart(
            $request->user(),
            $sessionId
        );

        $cart = $this->cartService->getCartWithItems($cart);
        $totals = $this->cartService->calculateTotals($cart);

        return $this->successResponse(new CartTotalsResource($totals), 'Cart totals retrieved successfully');
    }
}
