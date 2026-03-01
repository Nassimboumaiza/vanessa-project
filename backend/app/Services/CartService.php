<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Cart;
use App\Models\CartItem;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\DB;

class CartService
{
    public function __construct(
        private readonly ProductService $productService
    ) {}

    /**
     * Get or create cart for user or session.
     */
    public function getOrCreateCart(?User $user, ?string $sessionId): Cart
    {
        if ($user) {
            $cart = Cart::query()
                ->where('user_id', $user->id)
                ->first();

            if (! $cart) {
                $cart = Cart::create(['user_id' => $user->id]);
            }

            return $cart;
        }

        if (! $sessionId) {
            throw new \InvalidArgumentException('Either user or session ID is required');
        }

        $cart = Cart::query()
            ->where('session_id', $sessionId)
            ->first();

        if (! $cart) {
            $cart = Cart::create(['session_id' => $sessionId]);
        }

        return $cart;
    }

    /**
     * Get cart with loaded relationships.
     */
    public function getCartWithItems(Cart $cart): Cart
    {
        return $cart->load(['items.product.images', 'items.variant']);
    }

    /**
     * Add item to cart.
     *
     * @param array<string, mixed> $data
     */
    public function addItem(Cart $cart, array $data): CartItem
    {
        return DB::transaction(function () use ($cart, $data) {
            $product = $this->productService->findById($data['product_id']);

            if (! $product->is_active) {
                throw new \RuntimeException('Product is not available');
            }

            $variantId = $data['variant_id'] ?? null;
            $quantity = $data['quantity'];

            // Check stock availability
            if (! $this->productService->hasSufficientStock($product, $quantity, $variantId)) {
                throw new \RuntimeException('Insufficient stock available');
            }

            $price = $product->price;
            $variant = null;

            if ($variantId) {
                $variant = ProductVariant::query()
                    ->where('id', $variantId)
                    ->where('product_id', $product->id)
                    ->where('is_active', true)
                    ->first();

                if (! $variant) {
                    throw new \RuntimeException('Invalid product variant');
                }

                $price = $variant->price;
            }

            // Check if item already exists in cart
            $existingItem = $cart->items()
                ->where('product_id', $product->id)
                ->where('product_variant_id', $variantId)
                ->first();

            if ($existingItem) {
                $newQuantity = $existingItem->quantity + $quantity;

                if (! $this->productService->hasSufficientStock($product, $newQuantity, $variantId)) {
                    throw new \RuntimeException('Insufficient stock for requested quantity');
                }

                $existingItem->update([
                    'quantity' => $newQuantity,
                    'unit_price' => $price,
                    'total_price' => $price * $newQuantity,
                ]);

                return $existingItem->fresh();
            }

            return $cart->items()->create([
                'product_id' => $product->id,
                'product_variant_id' => $variantId,
                'quantity' => $quantity,
                'unit_price' => $price,
                'total_price' => $price * $quantity,
            ]);
        });
    }

    /**
     * Update cart item quantity.
     */
    public function updateItemQuantity(CartItem $item, int $quantity): CartItem
    {
        return DB::transaction(function () use ($item, $quantity) {
            if ($quantity <= 0) {
                $item->delete();

                return $item;
            }

            $product = $item->product;
            $variantId = $item->product_variant_id;

            if (! $this->productService->hasSufficientStock($product, $quantity, $variantId)) {
                throw new \RuntimeException('Insufficient stock for requested quantity');
            }

            $item->update([
                'quantity' => $quantity,
                'total_price' => $item->unit_price * $quantity,
            ]);

            return $item->fresh();
        });
    }

    /**
     * Remove item from cart.
     */
    public function removeItem(CartItem $item): void
    {
        $item->delete();
    }

    /**
     * Clear all items from cart.
     */
    public function clearCart(Cart $cart): void
    {
        $cart->items()->delete();
        $cart->update([
            'coupon_id' => null,
            'discount_amount' => 0,
            'total_items' => 0,
            'total_amount' => 0,
        ]);
    }

    /**
     * Batch sync cart items.
     *
     * @param array<int, array<string, mixed>> $items
     */
    public function batchSyncItems(Cart $cart, array $items): Collection
    {
        return DB::transaction(function () use ($cart, $items) {
            // Remove items not in the new list
            $newProductIds = collect($items)->pluck('product_id')->unique()->toArray();
            $cart->items()->whereNotIn('product_id', $newProductIds)->delete();

            $updatedItems = new Collection();

            foreach ($items as $itemData) {
                try {
                    $item = $this->addOrUpdateItem($cart, $itemData);
                    $updatedItems->push($item);
                } catch (\Exception $e) {
                    // Log error but continue processing other items
                    \Illuminate\Support\Facades\Log::warning('Failed to sync cart item', [
                        'cart_id' => $cart->id,
                        'item_data' => $itemData,
                        'error' => $e->getMessage(),
                    ]);
                }
            }

            return $updatedItems;
        });
    }

    /**
     * Add or update single item in batch sync.
     *
     * @param array<string, mixed> $data
     */
    private function addOrUpdateItem(Cart $cart, array $data): CartItem
    {
        $productId = $data['product_id'];
        $variantId = $data['variant_id'] ?? null;
        $quantity = $data['quantity'];

        $product = Product::query()->findOrFail($productId);

        if (! $product->is_active) {
            throw new \RuntimeException('Product is not available');
        }

        if (! $this->productService->hasSufficientStock($product, $quantity, $variantId)) {
            throw new \RuntimeException('Insufficient stock');
        }

        $existingItem = $cart->items()
            ->where('product_id', $productId)
            ->where('product_variant_id', $variantId)
            ->first();

        if ($existingItem) {
            $existingItem->update([
                'quantity' => $quantity,
                'total_price' => $existingItem->unit_price * $quantity,
            ]);

            return $existingItem->fresh();
        }

        $price = $product->price;

        if ($variantId) {
            $variant = ProductVariant::query()->find($variantId);
            if ($variant) {
                $price = $variant->price;
            }
        }

        return $cart->items()->create([
            'product_id' => $productId,
            'product_variant_id' => $variantId,
            'quantity' => $quantity,
            'unit_price' => $price,
            'total_price' => $price * $quantity,
        ]);
    }

    /**
     * Calculate cart totals.
     *
     * @return array<string, mixed>
     */
    public function calculateTotals(Cart $cart): array
    {
        $subtotal = (float) $cart->items->sum('total_price');
        $discountAmount = $cart->discount_amount !== null ? (float) $cart->discount_amount : 0.0;
        $taxRate = config('cart.tax_rate', 0);
        $taxAmount = ($subtotal - $discountAmount) * $taxRate;

        // Calculate shipping cost
        $freeShippingThreshold = config('cart.free_shipping_threshold', 100);
        $defaultShippingCost = config('cart.default_shipping_cost', 15);
        $shippingCost = $subtotal >= $freeShippingThreshold ? 0 : $defaultShippingCost;

        $total = $subtotal - $discountAmount + $taxAmount + $shippingCost;

        return [
            'subtotal' => round($subtotal, 2),
            'discount_amount' => round($discountAmount, 2),
            'shipping_cost' => round($shippingCost, 2),
            'tax_amount' => round($taxAmount, 2),
            'tax_rate' => $taxRate,
            'total' => round($total, 2),
            'item_count' => $cart->items->sum('quantity'),
        ];
    }

    /**
     * Transfer guest cart to user cart.
     */
    public function transferGuestCartToUser(string $sessionId, User $user): ?Cart
    {
        return DB::transaction(function () use ($sessionId, $user) {
            $guestCart = Cart::query()
                ->where('session_id', $sessionId)
                ->first();

            if (! $guestCart || $guestCart->items->isEmpty()) {
                return null;
            }

            $userCart = Cart::query()->firstOrCreate(
                ['user_id' => $user->id],
                ['user_id' => $user->id]
            );

            // Merge items
            foreach ($guestCart->items as $item) {
                $existingItem = $userCart->items()
                    ->where('product_id', $item->product_id)
                    ->where('product_variant_id', $item->product_variant_id)
                    ->first();

                if ($existingItem) {
                    $newQuantity = $existingItem->quantity + $item->quantity;
                    $existingItem->update([
                        'quantity' => $newQuantity,
                        'total_price' => $existingItem->unit_price * $newQuantity,
                    ]);
                } else {
                    $userCart->items()->create([
                        'product_id' => $item->product_id,
                        'product_variant_id' => $item->product_variant_id,
                        'quantity' => $item->quantity,
                        'unit_price' => $item->unit_price,
                        'total_price' => $item->total_price,
                    ]);
                }
            }

            $guestCart->delete();

            return $userCart->fresh();
        });
    }

    /**
     * Apply coupon to cart.
     */
    public function applyCoupon(Cart $cart, string $couponCode): array
    {
        $couponService = app(CouponService::class);
        return $couponService->applyCouponToCart($cart, $couponCode);
    }

    /**
     * Remove coupon from cart.
     */
    public function removeCoupon(Cart $cart): array
    {
        $couponService = app(CouponService::class);
        return $couponService->removeCouponFromCart($cart);
    }

    /**
     * Validate cart for checkout.
     *
     * @return array<string, mixed>
     */
    public function validateForCheckout(Cart $cart): array
    {
        $errors = [];
        $validItems = [];

        foreach ($cart->items as $item) {
            $product = $item->product;
            $variantId = $item->product_variant_id;

            if (! $product->is_active) {
                $errors[] = "Product '{$product->name}' is no longer available";
                continue;
            }

            if (! $this->productService->hasSufficientStock($product, $item->quantity, $variantId)) {
                $errors[] = "Insufficient stock for '{$product->name}'";
                continue;
            }

            $validItems[] = $item;
        }

        return [
            'valid' => empty($errors),
            'errors' => $errors,
            'valid_items' => $validItems,
        ];
    }
}
