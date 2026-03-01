<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Api\BaseController;
use App\Http\Requests\Api\V1\UpdateOrderStatusRequest;
use App\Http\Resources\Api\V1\OrderCollection;
use App\Http\Resources\Api\V1\OrderResource;
use App\Services\OrderService;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class OrderController extends BaseController
{
    public function __construct(
        private readonly OrderService $orderService
    ) {}

    /**
     * Display a listing of orders.
     */
    public function index(Request $request): JsonResponse
    {
        $filters = [
            'status' => $request->get('status'),
            'payment_status' => $request->get('payment_status'),
            'search' => $request->get('search'),
            'date_from' => $request->get('date_from'),
            'date_to' => $request->get('date_to'),
        ];

        $filters = array_filter($filters, fn ($value) => $value !== null);
        $perPage = (int) $request->get('per_page', config('api.pagination.default_per_page', 15));

        $orders = $this->orderService->getPaginatedOrders($filters, $perPage);

        return $this->paginatedResponse(new OrderCollection($orders), 'Orders retrieved successfully');
    }

    /**
     * Display the specified order.
     */
    public function show(int $id): JsonResponse
    {
        try {
            $order = $this->orderService->findByOrderNumber($id);

            return $this->successResponse(new OrderResource($order), 'Order retrieved successfully');
        } catch (ModelNotFoundException $e) {
            return $this->errorResponse('Order not found', 404);
        }
    }

    /**
     * Update order status.
     */
    public function updateStatus(UpdateOrderStatusRequest $request, int $id): JsonResponse
    {
        try {
            $order = $this->orderService->findById($id);
            $validated = $request->validated();

            $comment = $validated['notes'] ?? null;
            $order = $this->orderService->updateStatus($order, $validated['status'], $comment);

            return $this->successResponse(new OrderResource($order), 'Order status updated successfully');
        } catch (ModelNotFoundException $e) {
            return $this->errorResponse('Order not found', 404);
        } catch (\Exception $e) {
            return $this->errorResponse('Failed to update order status: ' . $e->getMessage(), 500);
        }
    }
}
