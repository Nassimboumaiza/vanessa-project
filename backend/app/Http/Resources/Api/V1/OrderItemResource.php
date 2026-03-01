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
     * Uses snapshot data as primary source to ensure historical accuracy.
     * Product relationship is optional and may not exist if product was deleted.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            
            // Product reference (may be null if product deleted)
            'product_id' => $this->product_id,
            'variant_id' => $this->variant_id,
            
            // Product snapshot (primary display source - immutable)
            'product_name' => $this->product_name,
            'product_slug' => $this->product_slug,
            'product_sku' => $this->product_sku,
            'product_image' => $this->product_image,
            
            // Variant snapshot
            'variant_name' => $this->variant_name,
            'variant_data' => $this->variant_data,
            
            // Pricing snapshot (at time of purchase)
            'quantity' => $this->quantity,
            'unit_price' => (float) $this->unit_price,
            'compare_price' => $this->compare_price ? (float) $this->compare_price : null,
            'discount_amount' => (float) $this->discount_amount,
            'tax_amount' => (float) $this->tax_amount,
            'tax_rate' => (float) $this->tax_rate,
            'total_price' => (float) $this->total_price,
            'currency' => $this->currency,
            
            // Discount percentage (calculated from snapshot)
            'discount_percentage' => $this->discount_percentage,
            
            // Refund tracking
            'refunded_quantity' => $this->refunded_quantity,
            'refunded_amount' => (float) $this->refunded_amount,
            'refundable_quantity' => $this->refundable_quantity,
            'refundable_amount' => $this->refundable_amount,
            
            // Timestamps
            'created_at' => $this->created_at?->toIso8601String(),
            
            // Product relationship (optional - only if product still exists)
            'product' => $this->whenLoaded('product', function () {
                return $this->product ? new ProductResource($this->product) : null;
            }),
            
            // Flag indicating if product still exists
            'product_exists' => $this->product()->exists(),
        ];
    }
}
