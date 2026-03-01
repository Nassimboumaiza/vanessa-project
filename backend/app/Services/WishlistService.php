<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Wishlist;
use App\Models\WishlistItem;
use App\Models\Product;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\DB;

class WishlistService
{
    /**
     * Get or create wishlist for user.
     */
    public function getOrCreateWishlist(User $user): Wishlist
    {
        $wishlist = Wishlist::query()
            ->where('user_id', $user->id)
            ->first();

        if (! $wishlist) {
            $wishlist = Wishlist::create(['user_id' => $user->id]);
        }

        return $wishlist;
    }

    /**
     * Get wishlist with items.
     */
    public function getWishlistWithItems(User $user): Wishlist
    {
        $wishlist = $this->getOrCreateWishlist($user);

        return $wishlist->load(['items.product.images', 'items.product.category']);
    }

    /**
     * Add item to wishlist.
     *
     * @throws \RuntimeException
     */
    public function addItem(User $user, Product $product): WishlistItem
    {
        return DB::transaction(function () use ($user, $product) {
            $wishlist = $this->getOrCreateWishlist($user);

            // Check if product already in wishlist
            $existingItem = WishlistItem::query()
                ->where('wishlist_id', $wishlist->id)
                ->where('product_id', $product->id)
                ->first();

            if ($existingItem) {
                throw new \RuntimeException('Product is already in your wishlist');
            }

            if (! $product->is_active) {
                throw new \RuntimeException('Product is not available');
            }

            return $wishlist->items()->create([
                'product_id' => $product->id,
            ]);
        });
    }

    /**
     * Remove item from wishlist.
     */
    public function removeItem(WishlistItem $item): void
    {
        $item->delete();
    }

    /**
     * Remove item by product ID.
     */
    public function removeItemByProduct(User $user, int $productId): void
    {
        $wishlist = $this->getOrCreateWishlist($user);

        WishlistItem::query()
            ->where('wishlist_id', $wishlist->id)
            ->where('product_id', $productId)
            ->delete();
    }

    /**
     * Check if product is in wishlist.
     */
    public function isInWishlist(User $user, int $productId): bool
    {
        $wishlist = $this->getOrCreateWishlist($user);

        return WishlistItem::query()
            ->where('wishlist_id', $wishlist->id)
            ->where('product_id', $productId)
            ->exists();
    }

    /**
     * Clear wishlist.
     */
    public function clearWishlist(User $user): void
    {
        $wishlist = $this->getOrCreateWishlist($user);
        $wishlist->items()->delete();
    }

    /**
     * Get wishlist items count.
     */
    public function getItemCount(User $user): int
    {
        $wishlist = $this->getOrCreateWishlist($user);

        return $wishlist->items()->count();
    }

    /**
     * Move wishlist item to cart.
     *
     * @throws \RuntimeException
     */
    public function moveToCart(WishlistItem $item, CartService $cartService, ?string $sessionId = null): void
    {
        DB::transaction(function () use ($item, $cartService, $sessionId) {
            $product = $item->product;

            if (! $product->is_active) {
                throw new \RuntimeException('Product is not available');
            }

            // Get cart
            $cart = $cartService->getOrCreateCart($item->wishlist->user, $sessionId);

            // Add to cart
            $cartService->addItem($cart, [
                'product_id' => $product->id,
                'variant_id' => null,
                'quantity' => 1,
            ]);

            // Remove from wishlist
            $item->delete();
        });
    }

    /**
     * Toggle wishlist item.
     */
    public function toggleItem(User $user, Product $product): array
    {
        $wishlist = $this->getOrCreateWishlist($user);

        $existingItem = WishlistItem::query()
            ->where('wishlist_id', $wishlist->id)
            ->where('product_id', $product->id)
            ->first();

        if ($existingItem) {
            $existingItem->delete();

            return [
                'action' => 'removed',
                'message' => 'Product removed from wishlist',
            ];
        }

        $this->addItem($user, $product);

        return [
            'action' => 'added',
            'message' => 'Product added to wishlist',
        ];
    }

    /**
     * Get wishlist item by ID.
     *
     * @throws ModelNotFoundException
     */
    public function findItemById(int $id): WishlistItem
    {
        return WishlistItem::query()->findOrFail($id);
    }

    /**
     * Get wishlist statistics.
     *
     * @return array<string, mixed>
     */
    public function getWishlistStatistics(): array
    {
        return [
            'total_wishlists' => Wishlist::query()->count(),
            'total_items' => WishlistItem::query()->count(),
            'most_wished_products' => WishlistItem::query()
                ->selectRaw('product_id, COUNT(*) as count')
                ->groupBy('product_id')
                ->orderByDesc('count')
                ->limit(10)
                ->with('product')
                ->get(),
        ];
    }
}
