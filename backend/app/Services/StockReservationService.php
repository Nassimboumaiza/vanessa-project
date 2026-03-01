<?php

namespace App\Services;

use App\Models\CartStockReservation;
use App\Models\Product;
use App\Models\ProductVariant;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class StockReservationService
{
    /**
     * Default reservation duration in minutes
     */
    public const DEFAULT_RESERVATION_MINUTES = 30;

    /**
     * Reserve stock for multiple cart items
     *
     * @param int $userId
     * @param array $items Array of ['product_id' => int, 'variant_id' => int|null, 'quantity' => int]
     * @param int $reservationMinutes
     * @return array{success: bool, reserved: array, failed: array, message: string}
     */
    public function reserveStock(int $userId, array $items, int $reservationMinutes = self::DEFAULT_RESERVATION_MINUTES): array
    {
        $reserved = [];
        $failed = [];

        if (empty($items)) {
            return [
                'success' => false,
                'reserved' => [],
                'failed' => [],
                'message' => 'No items provided for reservation.',
            ];
        }

        // Sort items by product_id/variant_id to reduce deadlocks
        usort($items, fn($a, $b) => ($a['product_id'] <=> $b['product_id']) ?: (($a['variant_id'] ?? 0) <=> ($b['variant_id'] ?? 0)));

        DB::beginTransaction();

        try {
            // Release any existing reservations for this user
            $this->releaseUserReservations($userId);

            foreach ($items as $item) {
                $productId = $item['product_id'];
                $variantId = $item['variant_id'] ?? null;
                $quantity = $item['quantity'];

                // Validate quantity
                if ($quantity <= 0) {
                    $failed[] = [
                        'product_id' => $productId,
                        'variant_id' => $variantId,
                        'error' => 'Invalid quantity requested',
                    ];
                    continue;
                }

                $result = $this->reserveItem($userId, $productId, $variantId, $quantity, $reservationMinutes);

                if ($result['success']) {
                    $reserved[] = $result['reservation'];
                } else {
                    $failed[] = [
                        'product_id' => $productId,
                        'variant_id' => $variantId,
                        'error' => $result['error'],
                    ];
                }
            }

            if (!empty($failed)) {
                DB::rollBack();
                Log::warning('Stock reservation failed', [
                    'user_id' => $userId,
                    'failed_items' => $failed,
                ]);

                return [
                    'success' => false,
                    'reserved' => [],
                    'failed' => $failed,
                    'message' => 'Some items could not be reserved: ' . collect($failed)->pluck('error')->implode(', '),
                ];
            }

            DB::commit();

            return [
                'success' => true,
                'reserved' => $reserved,
                'failed' => [],
                'message' => count($reserved) . ' item(s) reserved successfully',
            ];
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Stock reservation exception', [
                'user_id' => $userId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return [
                'success' => false,
                'reserved' => [],
                'failed' => $items,
                'message' => 'Stock reservation failed: ' . $e->getMessage(),
            ];
        }
    }

    /**
     * Reserve stock for a single item with concurrency protection
     */
    private function reserveItem(int $userId, int $productId, ?int $variantId, int $quantity, int $minutes): array
    {
        // Lock the row for update and calculate available stock including active reservations
        if ($variantId) {
            $variant = ProductVariant::where('id', $variantId)
                ->where('product_id', $productId)
                ->where('is_active', true)
                ->lockForUpdate()
                ->first();

            if (!$variant) {
                return ['success' => false, 'error' => 'Variant not found or inactive'];
            }

            $availableStock = $variant->stock_quantity
                - CartStockReservation::forProduct($productId, $variantId)
                    ->active()
                    ->sum('quantity_reserved');
        } else {
            $product = Product::where('id', $productId)
                ->where('is_active', true)
                ->lockForUpdate()
                ->first();

            if (!$product) {
                return ['success' => false, 'error' => 'Product not found or inactive'];
            }

            $availableStock = $product->stock_quantity
                - CartStockReservation::forProduct($productId, null)
                    ->active()
                    ->sum('quantity_reserved');
        }

        if ($availableStock < $quantity) {
            return [
                'success' => false,
                'error' => "Insufficient stock. Available: {$availableStock}, Requested: {$quantity}",
            ];
        }

        $reservation = CartStockReservation::create([
            'user_id' => $userId,
            'product_id' => $productId,
            'product_variant_id' => $variantId,
            'quantity_reserved' => $quantity,
            'expires_at' => Carbon::now()->addMinutes($minutes),
            'reservation_token' => CartStockReservation::generateToken(),
            'status' => CartStockReservation::STATUS_ACTIVE,
        ]);

        return ['success' => true, 'reservation' => $reservation];
    }

    /**
     * Release all active reservations for a user
     */
    public function releaseUserReservations(int $userId): int
    {
        $count = CartStockReservation::forUser($userId)
            ->active()
            ->update(['status' => CartStockReservation::STATUS_RELEASED]);

        Log::info('Released stock reservations', [
            'user_id' => $userId,
            'count' => $count,
        ]);

        return $count;
    }

    /**
     * Convert active reservations to order items atomically
     */
    public function convertReservationsToOrder(int $userId): array
    {
        DB::beginTransaction();

        try {
            $reservations = CartStockReservation::forUser($userId)
                ->active()
                ->lockForUpdate()
                ->get();

            $converted = [];
            $failed = [];

            foreach ($reservations as $reservation) {
                if ($reservation->convert()) {
                    $converted[] = $reservation;
                } else {
                    $failed[] = $reservation;
                }
            }

            DB::commit();

            return [
                'success' => empty($failed),
                'converted' => $converted,
                'failed' => $failed,
            ];
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to convert reservations to order', [
                'user_id' => $userId,
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'converted' => [],
                'failed' => $reservations ?? [],
            ];
        }
    }

    /**
     * Extend reservation expiration
     */
    public function extendReservations(int $userId, int $additionalMinutes = self::DEFAULT_RESERVATION_MINUTES): int
    {
        $count = CartStockReservation::forUser($userId)
            ->active()
            ->update([
                'expires_at' => DB::raw("DATE_ADD(expires_at, INTERVAL {$additionalMinutes} MINUTE)"),
            ]);

        Log::info('Extended stock reservations', [
            'user_id' => $userId,
            'count' => $count,
            'additional_minutes' => $additionalMinutes,
        ]);

        return $count;
    }

    /**
     * Get active reservations for a user
     */
    public function getActiveReservations(int $userId): array
    {
        return CartStockReservation::forUser($userId)
            ->active()
            ->with(['product', 'productVariant'])
            ->get()
            ->toArray();
    }

    /**
     * Check if items have active reservations
     */
    public function hasActiveReservations(int $userId, array $items): bool
    {
        foreach ($items as $item) {
            $exists = CartStockReservation::forUser($userId)
                ->forProduct($item['product_id'], $item['variant_id'] ?? null)
                ->active()
                ->exists();

            if (!$exists) {
                return false;
            }
        }

        return true;
    }

    /**
     * Clean up expired reservations (scheduled task)
     */
    public function cleanupExpiredReservations(): int
    {
        $count = CartStockReservation::expired()
            ->where('status', CartStockReservation::STATUS_ACTIVE)
            ->update(['status' => CartStockReservation::STATUS_EXPIRED]);

        Log::info('Cleaned up expired stock reservations', [
            'count' => $count,
        ]);

        return $count;
    }

    /**
     * Get available stock considering active reservations
     */
    public function getAvailableStock(int $productId, ?int $variantId = null): int
    {
        if ($variantId) {
            $variant = ProductVariant::find($variantId);
            if (!$variant) return 0;
            $totalStock = $variant->stock_quantity;
        } else {
            $product = Product::find($productId);
            if (!$product) return 0;
            $totalStock = $product->stock_quantity;
        }

        $reserved = CartStockReservation::forProduct($productId, $variantId)
            ->active()
            ->sum('quantity_reserved');

        return max(0, $totalStock - $reserved);
    }
}
