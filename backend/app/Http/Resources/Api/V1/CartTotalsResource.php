<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin array<string, mixed>
 */
class CartTotalsResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'subtotal' => $this->resource['subtotal'] ?? 0,
            'discount_amount' => $this->resource['discount_amount'] ?? 0,
            'tax_amount' => $this->resource['tax_amount'] ?? 0,
            'tax_rate' => $this->resource['tax_rate'] ?? 0,
            'total' => $this->resource['total'] ?? 0,
            'item_count' => $this->resource['item_count'] ?? 0,
        ];
    }
}
