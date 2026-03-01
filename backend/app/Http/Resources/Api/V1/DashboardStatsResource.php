<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin array<string, mixed>
 */
class DashboardStatsResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'overview' => $this->resource['overview'] ?? [],
            'orders_by_status' => $this->resource['orders_by_status'] ?? [],
            'recent_orders' => $this->resource['recent_orders'] ?? [],
            'top_products' => $this->resource['top_products'] ?? [],
            'sales_chart' => $this->resource['sales_chart'] ?? [],
        ];
    }
}
