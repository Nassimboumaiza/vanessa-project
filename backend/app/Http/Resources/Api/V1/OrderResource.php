<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class OrderResource extends JsonResource
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
            'order_number' => $this->order_number,
            'status' => $this->status,
            'payment_status' => $this->payment_status,
            'payment_method' => $this->payment_method,
            'currency' => $this->currency,
            // Pricing
            'subtotal' => $this->subtotal,
            'discount_amount' => $this->discount_amount,
            'shipping_amount' => $this->shipping_amount,
            'tax_amount' => $this->tax_amount,
            'total_amount' => $this->total_amount,
            // Shipping Address
            'shipping_address' => [
                'first_name' => $this->shipping_first_name,
                'last_name' => $this->shipping_last_name,
                'company' => $this->shipping_company,
                'address_line_1' => $this->shipping_address_line_1,
                'address_line_2' => $this->shipping_address_line_2,
                'city' => $this->shipping_city,
                'state' => $this->shipping_state,
                'postal_code' => $this->shipping_postal_code,
                'country' => $this->shipping_country,
                'phone' => $this->shipping_phone,
            ],
            // Billing Address
            'billing_address' => [
                'first_name' => $this->billing_first_name,
                'last_name' => $this->billing_last_name,
                'company' => $this->billing_company,
                'address_line_1' => $this->billing_address_line_1,
                'address_line_2' => $this->billing_address_line_2,
                'city' => $this->billing_city,
                'state' => $this->billing_state,
                'postal_code' => $this->billing_postal_code,
                'country' => $this->billing_country,
                'phone' => $this->billing_phone,
            ],
            'customer_notes' => $this->customer_notes,
            'coupon_code' => $this->coupon_code,
            'tracking_number' => $this->tracking_number,
            'carrier' => $this->carrier,
            'shipped_at' => $this->shipped_at?->toIso8601String(),
            'delivered_at' => $this->delivered_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
            // Nested relationships
            'user' => new UserResource($this->whenLoaded('user')),
            'items' => OrderItemResource::collection($this->whenLoaded('items')),
            'status_histories' => OrderStatusHistoryResource::collection($this->whenLoaded('statusHistories')),
        ];
    }
}
