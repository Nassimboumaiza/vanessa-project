<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\BaseController;
use App\Http\Requests\Api\V1\StoreOrderRequest;
use App\Http\Resources\Api\V1\OrderCollection;
use App\Http\Resources\Api\V1\OrderResource;
use App\Models\Cart;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\OrderStatusHistory;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class OrderController extends BaseController
{
    /**
     * Get user orders.
     */
    public function index(Request $request): JsonResponse
    {
        $perPage = (int) $request->get('per_page', config('api.pagination.default_per_page', 15));

        $orders = Order::where('user_id', $request->user()->id)
            ->with('items.product')
            ->orderBy('created_at', 'desc')
            ->paginate($perPage);

        return $this->paginatedResponse(new OrderCollection($orders), 'Orders retrieved successfully');
    }

    /**
     * Get single order.
     */
    public function show(Request $request, int $id): JsonResponse
    {
        $order = Order::where('user_id', $request->user()->id)
            ->where('id', $id)
            ->with(['items.product.images', 'statusHistories'])
            ->first();

        if (! $order) {
            return $this->errorResponse('Order not found', 404);
        }

        return $this->successResponse(new OrderResource($order), 'Order retrieved successfully');
    }

    /**
     * Create new order.
     */
    public function store(StoreOrderRequest $request): JsonResponse
    {
        $validated = $request->validated();

        $cart = Cart::where('user_id', $request->user()->id)
            ->with('items.product', 'items.variant')
            ->first();

        if (! $cart || $cart->items->isEmpty()) {
            return $this->errorResponse('Cart is empty', 400);
        }

        // Validate stock availability
        foreach ($cart->items as $item) {
            $product = $item->product;
            $variant = $item->variant;

            if (! $product->is_active) {
                return $this->errorResponse("Product '{$product->name}' is no longer available", 400);
            }

            if ($variant) {
                if ($variant->stock_quantity < $item->quantity) {
                    return $this->errorResponse("Insufficient stock for '{$product->name}' - {$variant->name}", 400);
                }
            } else {
                if ($product->stock_quantity < $item->quantity) {
                    return $this->errorResponse("Insufficient stock for '{$product->name}'", 400);
                }
            }
        }

        DB::beginTransaction();

        try {
            $subtotal = $cart->total_amount;
            $discountAmount = 0;
            $shippingAmount = $subtotal > 100 ? 0 : 15; // Free shipping over $100
            $taxAmount = $subtotal * 0.1; // 10% tax
            $totalAmount = $subtotal - $discountAmount + $shippingAmount + $taxAmount;

            $order = Order::create([
                'order_number' => $this->generateOrderNumber(),
                'user_id' => $request->user()->id,
                'status' => Order::STATUS_PENDING,
                'payment_status' => Order::PAYMENT_STATUS_PENDING,
                'payment_method' => $validated['payment_method'],
                'currency' => 'USD',
                'subtotal' => $subtotal,
                'discount_amount' => $discountAmount,
                'shipping_amount' => $shippingAmount,
                'tax_amount' => $taxAmount,
                'total_amount' => $totalAmount,
                'shipping_first_name' => $validated['shipping_address']['first_name'],
                'shipping_last_name' => $validated['shipping_address']['last_name'],
                'shipping_company' => $validated['shipping_address']['company'] ?? null,
                'shipping_address_line_1' => $validated['shipping_address']['address_line_1'],
                'shipping_address_line_2' => $validated['shipping_address']['address_line_2'] ?? null,
                'shipping_city' => $validated['shipping_address']['city'],
                'shipping_state' => $validated['shipping_address']['state'],
                'shipping_postal_code' => $validated['shipping_address']['postal_code'],
                'shipping_country' => $validated['shipping_address']['country'],
                'shipping_phone' => $validated['shipping_address']['phone'] ?? null,
                'billing_first_name' => $validated['billing_address']['first_name'],
                'billing_last_name' => $validated['billing_address']['last_name'],
                'billing_company' => $validated['billing_address']['company'] ?? null,
                'billing_address_line_1' => $validated['billing_address']['address_line_1'],
                'billing_address_line_2' => $validated['billing_address']['address_line_2'] ?? null,
                'billing_city' => $validated['billing_address']['city'],
                'billing_state' => $validated['billing_address']['state'],
                'billing_postal_code' => $validated['billing_address']['postal_code'],
                'billing_country' => $validated['billing_address']['country'],
                'billing_phone' => $validated['billing_address']['phone'] ?? null,
                'customer_notes' => $validated['customer_notes'] ?? null,
                'coupon_code' => $validated['coupon_code'] ?? null,
            ]);

            foreach ($cart->items as $cartItem) {
                OrderItem::create([
                    'order_id' => $order->id,
                    'product_id' => $cartItem->product_id,
                    'variant_id' => $cartItem->variant_id,
                    'product_name' => $cartItem->product->name,
                    'product_sku' => $cartItem->product->sku,
                    'variant_name' => $cartItem->variant?->name,
                    'quantity' => $cartItem->quantity,
                    'unit_price' => $cartItem->unit_price,
                    'discount_amount' => 0,
                    'tax_amount' => $cartItem->total_price * 0.1,
                    'total_price' => $cartItem->total_price,
                ]);

                // Update stock
                if ($cartItem->variant) {
                    $cartItem->variant->decrement('stock_quantity', $cartItem->quantity);
                } else {
                    $cartItem->product->decrement('stock_quantity', $cartItem->quantity);
                }
            }

            // Create status history
            OrderStatusHistory::create([
                'order_id' => $order->id,
                'status' => Order::STATUS_PENDING,
                'notes' => 'Order created - Cash on Delivery',
            ]);

            // Clear cart
            $cart->items()->delete();
            $cart->update(['total_amount' => 0, 'total_items' => 0]);

            DB::commit();

            return $this->successResponse(new OrderResource($order->fresh(['items', 'statusHistories'])), 'Order created successfully', 201);
        } catch (\Exception $e) {
            DB::rollBack();

            return $this->errorResponse('Failed to create order: '.$e->getMessage(), 500);
        }
    }

    /**
     * Get order tracking.
     */
    public function tracking(Request $request, int $id): JsonResponse
    {
        $order = Order::where('user_id', $request->user()->id)
            ->where('id', $id)
            ->first();

        if (! $order) {
            return $this->errorResponse('Order not found', 404);
        }

        $tracking = [
            'order_number' => $order->order_number,
            'status' => $order->status,
            'carrier' => $order->carrier,
            'tracking_number' => $order->tracking_number,
            'events' => $order->statusHistories()->orderBy('created_at', 'desc')->get()->map(function ($history) {
                return [
                    'date' => $history->created_at->toIso8601String(),
                    'status' => $history->status,
                    'description' => $this->getStatusDescription($history->status),
                    'notes' => $history->notes,
                ];
            }),
        ];

        return $this->successResponse($tracking, 'Tracking information retrieved');
    }

    /**
     * Generate unique order number.
     */
    private function generateOrderNumber(): string
    {
        $prefix = 'VP';
        $date = now()->format('Ymd');
        $random = strtoupper(Str::random(4));

        return "{$prefix}-{$date}-{$random}";
    }

    /**
     * Get status description for COD workflow.
     */
    private function getStatusDescription(string $status): string
    {
        return match ($status) {
            Order::STATUS_PENDING => 'Order received, awaiting confirmation',
            Order::STATUS_CONFIRMED => 'Order confirmed, preparing for processing',
            Order::STATUS_PREPARING => 'Order is being prepared',
            Order::STATUS_READY_FOR_DELIVERY => 'Order ready for delivery',
            Order::STATUS_OUT_FOR_DELIVERY => 'Order out for delivery',
            Order::STATUS_DELIVERED => 'Order delivered - Payment collected',
            Order::STATUS_CANCELLED => 'Order has been cancelled',
            Order::STATUS_REFUNDED => 'Order has been refunded',
            default => 'Status updated',
        };
    }
}
