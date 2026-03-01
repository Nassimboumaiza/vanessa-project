<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Cart;
use App\Models\User;
use App\Models\Product;
use App\Models\ProductVariant;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;
use RuntimeException;

/**
 * Order Service - COD-Ready E-commerce Order Management
 * 
 * Handles complete order lifecycle for Cash on Delivery (COD) orders:
 * - Order creation with product snapshot preservation
 * - COD-specific state management (pending → confirmed → preparing → delivered)
 * - Payment tracking and confirmation
 * - Atomic operations with database transactions
 */
class OrderService
{
    /**
     * Payment method constants
     */
    public const PAYMENT_METHOD_COD = 'cash_on_delivery';
    public const PAYMENT_METHOD_STRIPE = 'stripe';
    public const PAYMENT_METHOD_PAYPAL = 'paypal';

    public function __construct(
        private readonly CartService $cartService,
        private readonly ProductService $productService,
        private readonly StockReservationService $stockReservationService
    ) {}

    /**
     * Create order from cart with COD as default payment method.
     *
     * @param array<string, mixed> $orderData
     * @throws RuntimeException
     */
    public function createOrderFromCart(Cart $cart, User $user, array $orderData): Order
    {
        return DB::transaction(function () use ($cart, $user, $orderData) {
            // Validate cart
            $validation = $this->cartService->validateForCheckout($cart);
            if (! $validation['valid']) {
                throw new RuntimeException('Cart validation failed: ' . implode(', ', $validation['errors']));
            }

            if ($cart->items->isEmpty()) {
                throw new RuntimeException('Cart is empty');
            }

            // Calculate totals
            $totals = $this->cartService->calculateTotals($cart);

            // Determine payment method (default to COD)
            $paymentMethod = $orderData['payment_method'] ?? self::PAYMENT_METHOD_COD;

            // Create order with COD workflow fields
            $order = Order::create([
                'user_id' => $user->id,
                'order_number' => $this->generateOrderNumber(),
                'status' => Order::STATUS_PENDING,
                'payment_status' => Order::PAYMENT_STATUS_PENDING,
                'payment_method' => $paymentMethod,
                'currency' => $orderData['currency'] ?? config('app.currency', 'USD'),
                
                // Pricing
                'subtotal' => $totals['subtotal'],
                'discount_amount' => $totals['discount_amount'],
                'shipping_amount' => $totals['shipping_cost'] ?? 0,
                'tax_amount' => $totals['tax_amount'],
                'total_amount' => $totals['total'],
                
                // Shipping address
                'shipping_first_name' => $orderData['shipping_first_name'] ?? $user->first_name,
                'shipping_last_name' => $orderData['shipping_last_name'] ?? $user->last_name,
                'shipping_company' => $orderData['shipping_company'] ?? null,
                'shipping_address_line_1' => $orderData['shipping_address_line_1'],
                'shipping_address_line_2' => $orderData['shipping_address_line_2'] ?? null,
                'shipping_city' => $orderData['shipping_city'],
                'shipping_state' => $orderData['shipping_state'],
                'shipping_postal_code' => $orderData['shipping_postal_code'],
                'shipping_country' => $orderData['shipping_country'],
                'shipping_phone' => $orderData['shipping_phone'] ?? $user->phone,
                
                // Billing address (defaults to shipping if not provided)
                'billing_first_name' => $orderData['billing_first_name'] ?? $orderData['shipping_first_name'] ?? $user->first_name,
                'billing_last_name' => $orderData['billing_last_name'] ?? $orderData['shipping_last_name'] ?? $user->last_name,
                'billing_company' => $orderData['billing_company'] ?? $orderData['shipping_company'] ?? null,
                'billing_address_line_1' => $orderData['billing_address_line_1'] ?? $orderData['shipping_address_line_1'],
                'billing_address_line_2' => $orderData['billing_address_line_2'] ?? $orderData['shipping_address_line_2'] ?? null,
                'billing_city' => $orderData['billing_city'] ?? $orderData['shipping_city'],
                'billing_state' => $orderData['billing_state'] ?? $orderData['shipping_state'],
                'billing_postal_code' => $orderData['billing_postal_code'] ?? $orderData['shipping_postal_code'],
                'billing_country' => $orderData['billing_country'] ?? $orderData['shipping_country'],
                'billing_phone' => $orderData['billing_phone'] ?? $orderData['shipping_phone'] ?? $user->phone,
                
                // Additional info
                'customer_notes' => $orderData['customer_notes'] ?? null,
                'coupon_code' => $orderData['coupon_code'] ?? null,
                'idempotency_key' => $orderData['idempotency_key'] ?? null,
            ]);

            // Create order items with product snapshot and decrement stock
            foreach ($cart->items as $cartItem) {
                $this->createOrderItem($order, $cartItem);

                // Decrement actual stock
                $this->productService->decrementStock(
                    $cartItem->product,
                    $cartItem->quantity,
                    $cartItem->variant_id
                );
            }

            // Record initial status in history
            $order->statusHistories()->create([
                'status' => Order::STATUS_PENDING,
                'previous_status' => null,
                'notes' => 'Order created - ' . ($paymentMethod === self::PAYMENT_METHOD_COD ? 'Cash on Delivery' : 'Online Payment'),
            ]);

            // Clear cart after successful order creation
            $this->cartService->clearCart($cart);

            return $order->fresh(['items.product.images', 'items.variant', 'user', 'statusHistories']);
        });
    }

    /**
     * Create order item from cart item with complete product snapshot.
     * 
     * IMPORTANT: All product data is captured at order creation time to ensure
     * historical accuracy. Orders remain accurate even if products change or are deleted.
     */
    private function createOrderItem(Order $order, $cartItem): OrderItem
    {
        $product = $cartItem->product;
        $variant = $cartItem->variant;

        // Get primary product image
        $productImage = $product->images->firstWhere('is_primary', true)
            ?? $product->images->first();

        // Build variant data snapshot
        $variantData = null;
        if ($variant) {
            $variantData = [
                'id' => $variant->id,
                'name' => $variant->name,
                'sku' => $variant->sku,
                'volume_ml' => $variant->volume_ml,
                'price' => (float) $variant->price,
                'compare_price' => (float) $variant->compare_price,
            ];
        }

        // Determine pricing (variant price takes precedence if exists)
        $unitPrice = $variant ? $variant->price : $product->price;
        $comparePrice = $variant ? $variant->compare_price : $product->compare_price;

        // Calculate line item totals
        $subtotal = $unitPrice * $cartItem->quantity;
        $taxRate = config('cart.tax_rate', 0.20); // 20% default
        $taxAmount = $subtotal * $taxRate;
        $totalPrice = $subtotal + $taxAmount;

        return $order->items()->create([
            // Product reference (nullable - product may be deleted later)
            'product_id' => $product->id,
            'variant_id' => $variant?->id,
            
            // Product snapshot (immutable - primary data source for display)
            'product_name' => $product->name,
            'product_slug' => $product->slug,
            'product_sku' => $variant?->sku ?? $product->sku,
            'product_image' => $productImage?->image_path,
            
            // Variant snapshot
            'variant_name' => $variant?->name,
            'variant_data' => $variantData,
            
            // Pricing snapshot (at time of purchase)
            'quantity' => $cartItem->quantity,
            'unit_price' => $unitPrice,
            'compare_price' => $comparePrice,
            'discount_amount' => 0, // TODO: Apply coupon discounts at order level
            'tax_amount' => $taxAmount,
            'tax_rate' => $taxRate,
            'total_price' => $totalPrice,
            'currency' => config('app.currency', 'USD'),
            
            // Refund tracking (initialized to zero)
            'refunded_amount' => 0,
            'refunded_quantity' => 0,
        ]);
    }

    /**
     * Generate unique order number.
     */
    private function generateOrderNumber(): string
    {
        $prefix = config('order.prefix', 'ORD');
        $date = now()->format('Ymd');
        $random = strtoupper(Str::random(6));

        return "{$prefix}-{$date}-{$random}";
    }

    /**
     * Get user's orders.
     */
    public function getUserOrders(User $user, int $perPage = 10): LengthAwarePaginator
    {
        return Order::query()
            ->where('user_id', $user->id)
            ->with(['items.product.images'])
            ->orderBy('created_at', 'desc')
            ->paginate($perPage);
    }

    /**
     * Get order by ID for user.
     *
     * @throws ModelNotFoundException
     */
    public function getUserOrder(User $user, int $orderId): Order
    {
        return Order::query()
            ->where('user_id', $user->id)
            ->where('id', $orderId)
            ->with(['items.product', 'statusHistories'])
            ->firstOrFail();
    }

    /**
     * Get order by order number.
     *
     * @throws ModelNotFoundException
     */
    public function findByOrderNumber(string $orderNumber): Order
    {
        return Order::query()
            ->where('order_number', $orderNumber)
            ->with(['items.product', 'user', 'statusHistories'])
            ->firstOrFail();
    }

    /**
     * Get paginated orders for admin.
     *
     * @param array<string, mixed> $filters
     */
    public function getPaginatedOrders(array $filters = [], int $perPage = 15): LengthAwarePaginator
    {
        $query = Order::query()->with(['user', 'items']);

        if (! empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (! empty($filters['payment_status'])) {
            $query->where('payment_status', $filters['payment_status']);
        }

        if (! empty($filters['search'])) {
            $query->where(function ($q) use ($filters): void {
                $q->where('order_number', 'like', "%{$filters['search']}%")
                    ->orWhereHas('user', function ($user) use ($filters): void {
                        $user->where('email', 'like', "%{$filters['search']}%")
                            ->orWhere('first_name', 'like', "%{$filters['search']}%")
                            ->orWhere('last_name', 'like', "%{$filters['search']}%");
                    });
            });
        }

        if (! empty($filters['date_from'])) {
            $query->whereDate('created_at', '>=', $filters['date_from']);
        }

        if (! empty($filters['date_to'])) {
            $query->whereDate('created_at', '<=', $filters['date_to']);
        }

        $sortBy = $filters['sort_by'] ?? 'created_at';
        $sortOrder = $filters['sort_order'] ?? 'desc';

        return $query->orderBy($sortBy, $sortOrder)->paginate($perPage);
    }

    /**
     * Update order status with validation.
     *
     * Uses Order model's state machine for valid transitions.
     *
     * @throws InvalidArgumentException
     */
    public function updateStatus(Order $order, string $newStatus, ?string $notes = null): Order
    {
        // Validate transition using Order model's state machine
        if (! $order->canTransitionTo($newStatus)) {
            throw new InvalidArgumentException(
                "Invalid status transition from '{$order->status}' to '{$newStatus}'"
            );
        }

        return DB::transaction(function () use ($order, $newStatus, $notes) {
            $oldStatus = $order->status;

            $order->update(['status' => $newStatus]);

            // Record status history
            $order->statusHistories()->create([
                'status' => $newStatus,
                'previous_status' => $oldStatus,
                'notes' => $notes,
            ]);

            // Handle status-specific side effects
            $this->handleStatusChange($order, $newStatus, $oldStatus);

            return $order->fresh(['items', 'user', 'statusHistories']);
        });
    }

    /**
     * Handle status-specific side effects.
     */
    private function handleStatusChange(Order $order, string $newStatus, string $oldStatus): void
    {
        match ($newStatus) {
            Order::STATUS_CANCELLED => $this->handleOrderCancellation($order),
            Order::STATUS_REFUNDED => $this->handleOrderRefund($order),
            Order::STATUS_OUT_FOR_DELIVERY => $this->handleOrderShipment($order),
            Order::STATUS_DELIVERED => $this->handleOrderDelivery($order),
            default => null
        };
    }

    // ==========================================
    // COD-Specific State Management Methods
    // ==========================================

    /**
     * Confirm a pending COD order.
     *
     * Transition: pending → confirmed
     * Used when admin confirms the order for processing.
     *
     * @throws InvalidArgumentException
     */
    public function confirmOrder(Order $order, ?string $notes = null): Order
    {
        return $this->updateStatus(
            $order,
            Order::STATUS_CONFIRMED,
            $notes ?? 'Order confirmed by admin'
        );
    }

    /**
     * Mark order as preparing (warehouse preparation).
     *
     * Transition: confirmed → preparing
     *
     * @throws InvalidArgumentException
     */
    public function prepareOrder(Order $order, ?string $notes = null): Order
    {
        return $this->updateStatus(
            $order,
            Order::STATUS_PREPARING,
            $notes ?? 'Order is being prepared'
        );
    }

    /**
     * Mark order as ready for delivery.
     *
     * Transition: preparing → ready_for_delivery
     *
     * @throws InvalidArgumentException
     */
    public function readyForDelivery(Order $order, ?string $notes = null): Order
    {
        return $this->updateStatus(
            $order,
            Order::STATUS_READY_FOR_DELIVERY,
            $notes ?? 'Order is ready for delivery'
        );
    }

    /**
     * Ship order (mark as out for delivery).
     *
     * Transition: ready_for_delivery → out_for_delivery
     *
     * @param array<string, mixed> $shippingData
     * @throws InvalidArgumentException
     */
    public function shipOrder(Order $order, array $shippingData = [], ?string $notes = null): Order
    {
        return DB::transaction(function () use ($order, $shippingData, $notes) {
            // Validate transition
            if (! $order->canTransitionTo(Order::STATUS_OUT_FOR_DELIVERY)) {
                throw new InvalidArgumentException(
                    "Order cannot be shipped from '{$order->status}' status"
                );
            }

            $oldStatus = $order->status;

            // Update order with shipping info
            $order->update([
                'status' => Order::STATUS_OUT_FOR_DELIVERY,
                'tracking_number' => $shippingData['tracking_number'] ?? $order->tracking_number,
                'carrier' => $shippingData['carrier'] ?? $order->carrier,
                'shipped_at' => now(),
            ]);

            // Record status history
            $order->statusHistories()->create([
                'status' => Order::STATUS_OUT_FOR_DELIVERY,
                'previous_status' => $oldStatus,
                'notes' => $notes ?? 'Order shipped - out for delivery',
            ]);

            // Release stock reservations
            $this->handleOrderShipment($order);

            return $order->fresh(['items', 'user', 'statusHistories']);
        });
    }

    /**
     * Mark order as delivered and confirm COD payment.
     *
     * Transition: out_for_delivery → delivered
     * For COD orders, this automatically marks payment as received.
     *
     * @throws InvalidArgumentException
     */
    public function deliverOrder(Order $order, ?string $notes = null): Order
    {
        return DB::transaction(function () use ($order, $notes) {
            // Validate transition
            if (! $order->canTransitionTo(Order::STATUS_DELIVERED)) {
                throw new InvalidArgumentException(
                    "Order cannot be delivered from '{$order->status}' status"
                );
            }

            $oldStatus = $order->status;

            // Update order status and delivery timestamp
            $order->update([
                'status' => Order::STATUS_DELIVERED,
                'delivered_at' => now(),
            ]);

            // Record status history
            $order->statusHistories()->create([
                'status' => Order::STATUS_DELIVERED,
                'previous_status' => $oldStatus,
                'notes' => $notes ?? 'Order delivered successfully',
            ]);

            // For COD orders, automatically confirm payment
            if ($order->payment_method === self::PAYMENT_METHOD_COD) {
                $this->confirmCODPayment($order, 'Payment collected on delivery');
            }

            return $order->fresh(['items', 'user', 'statusHistories']);
        });
    }

    /**
     * Confirm COD payment (mark as paid).
     *
     * Can be called manually by admin or automatically on delivery.
     *
     * @throws RuntimeException
     */
    public function confirmCODPayment(Order $order, ?string $notes = null): Order
    {
        // Validate this is a COD order
        if ($order->payment_method !== self::PAYMENT_METHOD_COD) {
            throw new RuntimeException('This order is not a Cash on Delivery order');
        }

        // Check if already paid
        if ($order->payment_status === Order::PAYMENT_STATUS_PAID) {
            throw new RuntimeException('Order is already marked as paid');
        }

        return DB::transaction(function () use ($order, $notes) {
            $order->update([
                'payment_status' => Order::PAYMENT_STATUS_PAID,
                'paid_at' => now(),
            ]);

            // Record payment in status history
            $order->statusHistories()->create([
                'status' => $order->status,
                'previous_status' => $order->status,
                'notes' => $notes ?? 'COD payment confirmed - cash collected',
            ]);

            return $order->fresh();
        });
    }

    /**
     * Cancel order with reason.
     *
     * Restores stock for products that still exist.
     *
     * @throws InvalidArgumentException
     */
    public function cancelOrder(Order $order, string $reason, ?string $notes = null): Order
    {
        return DB::transaction(function () use ($order, $reason, $notes) {
            // Validate transition
            if (! $order->canTransitionTo(Order::STATUS_CANCELLED)) {
                throw new InvalidArgumentException(
                    "Order cannot be cancelled from '{$order->status}' status"
                );
            }

            $oldStatus = $order->status;

            // Update order status
            $order->update([
                'status' => Order::STATUS_CANCELLED,
                'payment_status' => Order::PAYMENT_STATUS_PENDING === $order->payment_status 
                    ? 'cancelled' 
                    : $order->payment_status,
            ]);

            // Record status history
            $order->statusHistories()->create([
                'status' => Order::STATUS_CANCELLED,
                'previous_status' => $oldStatus,
                'notes' => $notes ?? "Order cancelled: {$reason}",
            ]);

            // Restore stock
            $this->handleOrderCancellation($order);

            return $order->fresh(['items', 'user', 'statusHistories']);
        });
    }

    /**
     * Process refund for delivered order.
     *
     * Transition: delivered → refunded
     *
     * @throws InvalidArgumentException
     */
    public function refundOrder(Order $order, ?string $reason = null): Order
    {
        return DB::transaction(function () use ($order, $reason) {
            // Validate transition
            if (! $order->canTransitionTo(Order::STATUS_REFUNDED)) {
                throw new InvalidArgumentException(
                    "Order cannot be refunded from '{$order->status}' status"
                );
            }

            $oldStatus = $order->status;

            // Update order status
            $order->update([
                'status' => Order::STATUS_REFUNDED,
                'payment_status' => 'refunded',
            ]);

            // Record status history
            $order->statusHistories()->create([
                'status' => Order::STATUS_REFUNDED,
                'previous_status' => $oldStatus,
                'notes' => $reason ?? 'Order refunded',
            ]);

            return $order->fresh(['items', 'user', 'statusHistories']);
        });
    }

    /**
     * Handle order cancellation.
     * 
     * Safely handles cases where products may have been deleted after order creation.
     * Stock is only restored for products that still exist.
     */
    private function handleOrderCancellation(Order $order): void
    {
        // Release stock reservations and restore stock
        foreach ($order->items as $item) {
            // Only restore stock if product still exists
            if ($item->product) {
                $this->stockReservationService->releaseStock($order, $item->product);
                $this->productService->incrementStock(
                    $item->product,
                    $item->quantity,
                    $item->variant_id
                );
            }
        }

        $order->update(['payment_status' => 'cancelled']);
    }

    /**
     * Handle order refund.
     */
    private function handleOrderRefund(Order $order): void
    {
        $order->update(['payment_status' => 'refunded']);
    }

    /**
     * Handle order shipment.
     * 
     * Safely handles cases where products may have been deleted after order creation.
     */
    private function handleOrderShipment(Order $order): void
    {
        // Release stock reservations (stock already decremented)
        foreach ($order->items as $item) {
            // Only release reservation if product still exists
            if ($item->product) {
                $this->stockReservationService->releaseStock($order, $item->product);
            }
        }
    }

    /**
     * Handle order delivery.
     */
    private function handleOrderDelivery(Order $order): void
    {
        // Delivery-specific logic can be added here
        // e.g., send delivery confirmation notification
    }

    /**
     * Get order statistics for admin dashboard.
     *
     * @return array<string, mixed>
     */
    public function getOrderStatistics(): array
    {
        return [
            'total_orders' => Order::query()->count(),
            'pending_orders' => Order::query()->where('status', Order::STATUS_PENDING)->count(),
            'confirmed_orders' => Order::query()->where('status', Order::STATUS_CONFIRMED)->count(),
            'preparing_orders' => Order::query()->where('status', Order::STATUS_PREPARING)->count(),
            'ready_for_delivery_orders' => Order::query()->where('status', Order::STATUS_READY_FOR_DELIVERY)->count(),
            'out_for_delivery_orders' => Order::query()->where('status', Order::STATUS_OUT_FOR_DELIVERY)->count(),
            'delivered_orders' => Order::query()->where('status', Order::STATUS_DELIVERED)->count(),
            'cancelled_orders' => Order::query()->where('status', Order::STATUS_CANCELLED)->count(),
            'refunded_orders' => Order::query()->where('status', Order::STATUS_REFUNDED)->count(),
            'total_revenue' => (float) Order::query()
                ->where('payment_status', Order::PAYMENT_STATUS_PAID)
                ->sum('total_amount'),
            'pending_revenue' => (float) Order::query()
                ->where('payment_status', Order::PAYMENT_STATUS_PENDING)
                ->whereNotIn('status', [Order::STATUS_CANCELLED, Order::STATUS_REFUNDED])
                ->sum('total_amount'),
            'today_revenue' => (float) Order::query()
                ->where('payment_status', Order::PAYMENT_STATUS_PAID)
                ->whereDate('created_at', today())
                ->sum('total_amount'),
            'cod_pending_collection' => (float) Order::query()
                ->where('payment_method', self::PAYMENT_METHOD_COD)
                ->where('payment_status', Order::PAYMENT_STATUS_PENDING)
                ->whereNotIn('status', [Order::STATUS_CANCELLED, Order::STATUS_REFUNDED])
                ->sum('total_amount'),
        ];
    }

    /**
     * Get recent orders.
     */
    public function getRecentOrders(int $limit = 5): Collection
    {
        return Order::query()
            ->with('user')
            ->orderBy('created_at', 'desc')
            ->limit($limit)
            ->get();
    }

    /**
     * Process order payment (extensible for future payment gateways).
     *
     * @param array<string, mixed> $paymentData
     * @return array<string, mixed>
     * @throws RuntimeException
     */
    public function processPayment(Order $order, array $paymentData): array
    {
        $paymentMethod = $paymentData['payment_method'] ?? self::PAYMENT_METHOD_COD;

        return match ($paymentMethod) {
            self::PAYMENT_METHOD_STRIPE => $this->processStripePayment($order, $paymentData),
            self::PAYMENT_METHOD_PAYPAL => $this->processPaypalPayment($order, $paymentData),
            self::PAYMENT_METHOD_COD => $this->processCashOnDelivery($order),
            default => throw new RuntimeException("Unsupported payment method: {$paymentMethod}")
        };
    }

    /**
     * Process Stripe payment.
     *
     * @param array<string, mixed> $paymentData
     * @return array<string, mixed>
     * @throws RuntimeException
     */
    private function processStripePayment(Order $order, array $paymentData): array
    {
        // Placeholder for Stripe integration
        // Future implementation: integrate with Stripe API
        throw new RuntimeException('Stripe payment not implemented');
    }

    /**
     * Process PayPal payment.
     *
     * @param array<string, mixed> $paymentData
     * @return array<string, mixed>
     * @throws RuntimeException
     */
    private function processPaypalPayment(Order $order, array $paymentData): array
    {
        // Placeholder for PayPal integration
        // Future implementation: integrate with PayPal API
        throw new RuntimeException('PayPal payment not implemented');
    }

    /**
     * Process Cash on Delivery.
     *
     * @return array<string, mixed>
     */
    private function processCashOnDelivery(Order $order): array
    {
        // COD orders remain pending until delivery
        // Payment is confirmed when order is delivered
        $order->update([
            'payment_method' => self::PAYMENT_METHOD_COD,
            'payment_status' => Order::PAYMENT_STATUS_PENDING,
        ]);

        return [
            'success' => true,
            'message' => 'Order confirmed for Cash on Delivery',
            'order_id' => $order->id,
            'order_number' => $order->order_number,
            'payment_method' => self::PAYMENT_METHOD_COD,
            'payment_status' => Order::PAYMENT_STATUS_PENDING,
        ];
    }

    // ==========================================
    // Utility Methods
    // ==========================================

    /**
     * Check if order can be cancelled.
     */
    public function canCancel(Order $order): bool
    {
        return $order->canTransitionTo(Order::STATUS_CANCELLED);
    }

    /**
     * Check if order can be refunded.
     */
    public function canRefund(Order $order): bool
    {
        return $order->canTransitionTo(Order::STATUS_REFUNDED);
    }

    /**
     * Get next valid statuses for an order.
     *
     * @return array<int, string>
     */
    public function getValidNextStatuses(Order $order): array
    {
        return match ($order->status) {
            Order::STATUS_PENDING => [Order::STATUS_CONFIRMED, Order::STATUS_CANCELLED],
            Order::STATUS_CONFIRMED => [Order::STATUS_PREPARING, Order::STATUS_CANCELLED],
            Order::STATUS_PREPARING => [Order::STATUS_READY_FOR_DELIVERY, Order::STATUS_CANCELLED],
            Order::STATUS_READY_FOR_DELIVERY => [Order::STATUS_OUT_FOR_DELIVERY, Order::STATUS_CANCELLED],
            Order::STATUS_OUT_FOR_DELIVERY => [Order::STATUS_DELIVERED, Order::STATUS_CANCELLED],
            Order::STATUS_DELIVERED => [Order::STATUS_REFUNDED],
            Order::STATUS_CANCELLED, Order::STATUS_REFUNDED => [],
            default => [],
        };
    }
}
