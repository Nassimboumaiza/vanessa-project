<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ProductImageResource extends JsonResource
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
            'image_path' => $this->image_path,
            'alt_text' => $this->alt_text,
            'sort_order' => $this->sort_order,
            'is_primary' => $this->is_primary,
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
