<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class OrderItemResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'product_id' => $this->product_id,
            'variant_id' => $this->variant_id,
            'product_name' => $this->product_name,
            'product_sku' => $this->product_sku,
            'variant_name' => $this->variant_name,
            'quantity' => $this->quantity,
            'unit_price' => $this->unit_price,
            'discount_amount' => $this->discount_amount,
            'tax_amount' => $this->tax_amount,
            'total_price' => $this->total_price,
            'created_at' => $this->created_at?->toIso8601String(),
            // Include product relationship when loaded
            'product' => new ProductResource($this->whenLoaded('product')),
        ];
    }
}
