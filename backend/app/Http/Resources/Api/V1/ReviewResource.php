<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ReviewResource extends JsonResource
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
            'rating' => $this->rating,
            'title' => $this->title,
            'content' => $this->content,
            'pros' => $this->pros,
            'cons' => $this->cons,
            'is_verified_purchase' => $this->is_verified_purchase,
            'is_approved' => $this->is_approved,
            'helpful_count' => $this->helpful_count,
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
            // Nested relationships
            'user' => new UserResource($this->whenLoaded('user')),
            'product' => new ProductResource($this->whenLoaded('product')),
        ];
    }
}
