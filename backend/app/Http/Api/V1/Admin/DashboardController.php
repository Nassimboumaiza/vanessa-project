<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Api\BaseController;
use App\Http\Resources\Api\V1\OrderResource;
use App\Models\Order;
use App\Models\Product;
use App\Models\Review;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class DashboardController extends BaseController
{
    /**
     * Get dashboard statistics.
     */
    public function index(Request $request): JsonResponse
    {
        $startDate = $request->get('start_date', now()->subDays(30)->toDateString());
        $endDate = $request->get('end_date', now()->toDateString());

        $stats = [
            'overview' => [
                'total_revenue' => Order::where('payment_status', 'paid')
                    ->whereBetween('created_at', [$startDate, $endDate])
                    ->sum('total_amount'),
                'total_orders' => Order::whereBetween('created_at', [$startDate, $endDate])->count(),
                'total_customers' => User::where('role', 'customer')->whereBetween('created_at', [$startDate, $endDate])->count(),
                'total_products' => Product::count(),
                'low_stock_products' => Product::where('stock_quantity', '<=', 10)->count(),
                'pending_reviews' => Review::where('is_approved', false)->count(),
            ],
            'orders_by_status' => Order::whereBetween('created_at', [$startDate, $endDate])
                ->select('status', DB::raw('COUNT(*) as count'))
                ->groupBy('status')
                ->pluck('count', 'status')
                ->toArray(),
            'recent_orders' => OrderResource::collection(
                Order::with(['user', 'items'])
                    ->orderBy('created_at', 'desc')
                    ->limit(5)
                    ->get()
            ),
            'top_products' => DB::table('order_items')
                ->join('orders', 'order_items.order_id', '=', 'orders.id')
                ->where('orders.payment_status', 'paid')
                ->whereBetween('orders.created_at', [$startDate, $endDate])
                ->select('order_items.product_name', DB::raw('SUM(order_items.quantity) as total_sold'))
                ->groupBy('order_items.product_name')
                ->orderByDesc('total_sold')
                ->limit(5)
                ->get()
                ->toArray(),
            'sales_chart' => $this->getSalesChartData($startDate, $endDate),
        ];

        return $this->successResponse($stats, 'Dashboard statistics retrieved');
    }

    /**
     * Get sales chart data.
     */
    private function getSalesChartData(string $startDate, string $endDate): array
    {
        $orders = Order::where('payment_status', 'paid')
            ->whereBetween('created_at', [$startDate, $endDate])
            ->select(
                DB::raw('DATE(created_at) as date'),
                DB::raw('COUNT(*) as orders'),
                DB::raw('SUM(total_amount) as revenue')
            )
            ->groupBy('date')
            ->orderBy('date')
            ->get();

        return $orders->map(function ($item) {
            return [
                'date' => $item->date,
                'orders' => (int) $item->orders,
                'revenue' => (float) $item->revenue,
            ];
        })->toArray();
    }
}
