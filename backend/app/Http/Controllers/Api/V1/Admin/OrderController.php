<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Api\BaseController;
use App\Http\Requests\Api\V1\UpdateOrderStatusRequest;
use App\Http\Resources\Api\V1\OrderCollection;
use App\Http\Resources\Api\V1\OrderResource;
use App\Models\Order;
use App\Models\OrderStatusHistory;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class OrderController extends BaseController
{
    /**
     * Display a listing of orders.
     */
    public function index(Request $request): JsonResponse
    {
        $query = Order::with(['user', 'items.product']);

        if ($request->has('status')) {
            $query->where('status', $request->get('status'));
        }

        if ($request->has('payment_status')) {
            $query->where('payment_status', $request->get('payment_status'));
        }

        if ($request->has('search')) {
            $search = $request->get('search');
            $query->where(function ($q) use ($search): void {
                $q->where('order_number', 'like', "%{$search}%")
                    ->orWhereHas('user', function ($userQuery) use ($search): void {
                        $userQuery->where('email', 'like', "%{$search}%")
                            ->orWhere('name', 'like', "%{$search}%");
                    });
            });
        }

        if ($request->has('date_from')) {
            $query->where('created_at', '>=', $request->get('date_from'));
        }

        if ($request->has('date_to')) {
            $query->where('created_at', '<=', $request->get('date_to'));
        }

        $perPage = (int) $request->get('per_page', config('api.pagination.default_per_page', 15));
        $orders = $query->orderBy('created_at', 'desc')->paginate($perPage);

        return $this->paginatedResponse(new OrderCollection($orders), 'Orders retrieved successfully');
    }

    /**
     * Display the specified order.
     */
    public function show(int $id): JsonResponse
    {
        $order = Order::with(['user', 'items.product', 'statusHistories.user'])->findOrFail($id);

        return $this->successResponse(new OrderResource($order), 'Order retrieved successfully');
    }

    /**
     * Update order status.
     */
    public function updateStatus(UpdateOrderStatusRequest $request, int $id): JsonResponse
    {
        $order = Order::findOrFail($id);
        $validated = $request->validated();

        DB::beginTransaction();

        try {
            $oldStatus = $order->status;
            $newStatus = $validated['status'];

            $updateData = ['status' => $newStatus];

            if (in_array($newStatus, ['shipped', 'delivered'], true)) {
                if ($validated['tracking_number']) {
                    $updateData['tracking_number'] = $validated['tracking_number'];
                }
                if ($validated['carrier']) {
                    $updateData['carrier'] = $validated['carrier'];
                }
            }

            if ($newStatus === 'shipped') {
                $updateData['shipped_at'] = now();
            }

            if ($newStatus === 'delivered') {
                $updateData['delivered_at'] = now();
            }

            $order->update($updateData);

            // Create status history
            OrderStatusHistory::create([
                'order_id' => $order->id,
                'status' => $newStatus,
                'notes' => $validated['notes'] ?? "Status changed from {$oldStatus} to {$newStatus}",
                'user_id' => $request->user()->id,
            ]);

            DB::commit();

            return $this->successResponse(new OrderResource($order->fresh()), 'Order status updated successfully');
        } catch (\Exception $e) {
            DB::rollBack();

            return $this->errorResponse('Failed to update order status: ' . $e->getMessage(), 500);
        }
    }
}
