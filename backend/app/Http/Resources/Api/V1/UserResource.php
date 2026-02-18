<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UserResource extends JsonResource
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
            'name' => $this->name,
            'email' => $this->email,
            'phone' => $this->phone,
            'avatar' => $this->avatar,
            'gender' => $this->gender,
            'birthdate' => $this->birthdate,
            'role' => $this->role,
            'is_active' => $this->is_active,
            'preferred_language' => $this->preferred_language,
            'preferred_currency' => $this->preferred_currency,
            'email_verified_at' => $this->email_verified_at,
            'last_login_at' => $this->last_login_at,
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
            // Include relationships when loaded
            'addresses' => AddressResource::collection($this->whenLoaded('addresses')),
            'orders' => OrderResource::collection($this->whenLoaded('orders')),
            'reviews' => ReviewResource::collection($this->whenLoaded('reviews')),
        ];
    }
}
