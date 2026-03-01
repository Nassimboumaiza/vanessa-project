<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\BaseController;
use App\Http\Requests\Api\V1\StoreOrderRequest;
use App\Http\Resources\Api\V1\OrderCollection;
use App\Http\Resources\Api\V1\OrderResource;
use App\Services\CartService;
use App\Services\OrderService;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class OrderController extends BaseController
{
    public function __construct(
        private readonly OrderService $orderService,
        private readonly CartService $cartService
    ) {}

    /**
     * Get user orders.
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        if (! $user) {
            return $this->errorResponse('Unauthenticated', 401);
        }

        $perPage = (int) $request->get('per_page', config('api.pagination.default_per_page', 15));

        $orders = $this->orderService->getUserOrders($user, $perPage);

        return $this->paginatedResponse(new OrderCollection($orders), 'Orders retrieved successfully');
    }

    /**
     * Get single order.
     */
    public function show(Request $request, int $id): JsonResponse
    {
        try {
            $order = $this->orderService->getUserOrder($request->user(), $id);

            return $this->successResponse(new OrderResource($order), 'Order retrieved successfully');
        } catch (ModelNotFoundException $e) {
            return $this->errorResponse('Order not found', 404);
        }
    }

    /**
     * Create new order.
     */
    public function store(StoreOrderRequest $request): JsonResponse
    {
        $user = $request->user();
        $validated = $request->validated();

        // Get user's cart - use null for sessionId in API context (session not available)
        $sessionId = $request->hasSession() ? $request->session()->getId() : null;
        $cart = $this->cartService->getOrCreateCart($user, $sessionId);
        $cart = $this->cartService->getCartWithItems($cart);

        if ($cart->items->isEmpty()) {
            return $this->errorResponse('Cart is empty', 400);
        }

        // Validate cart for checkout
        $validation = $this->cartService->validateForCheckout($cart);
        if (! $validation['valid']) {
            return $this->errorResponse(implode(', ', $validation['errors']), 400);
        }

        try {
            $shipping = $validated['shipping_address'] ?? [];
            $billing = $validated['billing_address'] ?? [];

            $order = $this->orderService->createOrderFromCart($cart, $user, [
                'shipping_first_name' => $shipping['first_name'] ?? null,
                'shipping_last_name' => $shipping['last_name'] ?? null,
                'shipping_company' => $shipping['company'] ?? null,
                'shipping_address_line_1' => $shipping['address_line_1'] ?? null,
                'shipping_address_line_2' => $shipping['address_line_2'] ?? null,
                'shipping_city' => $shipping['city'] ?? null,
                'shipping_state' => $shipping['state'] ?? null,
                'shipping_postal_code' => $shipping['postal_code'] ?? null,
                'shipping_country' => $shipping['country'] ?? null,
                'shipping_phone' => $shipping['phone'] ?? null,
                'billing_first_name' => $billing['first_name'] ?? null,
                'billing_last_name' => $billing['last_name'] ?? null,
                'billing_company' => $billing['company'] ?? null,
                'billing_address_line_1' => $billing['address_line_1'] ?? null,
                'billing_address_line_2' => $billing['address_line_2'] ?? null,
                'billing_city' => $billing['city'] ?? null,
                'billing_state' => $billing['state'] ?? null,
                'billing_postal_code' => $billing['postal_code'] ?? null,
                'billing_country' => $billing['country'] ?? null,
                'billing_phone' => $billing['phone'] ?? null,
                'shipping_method' => $validated['shipping_method'] ?? 'standard',
                'payment_method' => $validated['payment_method'] ?? 'cod',
                'customer_notes' => $validated['customer_notes'] ?? null,
                'coupon_code' => $validated['coupon_code'] ?? null,
                'idempotency_key' => $validated['idempotency_key'] ?? null,
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
            ]);

            return $this->successResponse(new OrderResource($order), 'Order created successfully', 201);
        } catch (\RuntimeException $e) {
            return $this->errorResponse($e->getMessage(), 400);
        } catch (\Exception $e) {
            return $this->errorResponse('Failed to create order: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Get order tracking.
     */
    public function tracking(Request $request, int $id): JsonResponse
    {
        try {
            $order = $this->orderService->getUserOrder($request->user(), $id);

            $tracking = [
                'order_number' => $order->order_number,
                'status' => $order->status,
                'carrier' => $order->carrier,
                'tracking_number' => $order->tracking_number,
                'events' => $order->statusHistories->map(function ($history) {
                    return [
                        'date' => $history->created_at->toIso8601String(),
                        'status' => $history->status,
                        'description' => $this->getStatusDescription($history->status),
                        'notes' => $history->notes,
                    ];
                }),
            ];

            return $this->successResponse($tracking, 'Tracking information retrieved');
        } catch (ModelNotFoundException $e) {
            return $this->errorResponse('Order not found', 404);
        }
    }

    /**
     * Get status description for COD workflow.
     */
    private function getStatusDescription(string $status): string
    {
        return match ($status) {
            'pending' => 'Order received, awaiting confirmation',
            'processing' => 'Order is being processed',
            'shipped' => 'Order has been shipped',
            'delivered' => 'Order has been delivered',
            'cancelled' => 'Order has been cancelled',
            'refunded' => 'Order has been refunded',
            default => 'Status updated',
        };
    }
}
