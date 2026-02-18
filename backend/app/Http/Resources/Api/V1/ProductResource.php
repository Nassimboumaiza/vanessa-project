<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ProductResource extends JsonResource
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
            'slug' => $this->slug,
            'description' => $this->description,
            'short_description' => $this->short_description,
            'price' => $this->price,
            'compare_price' => $this->compare_price,
            'cost_price' => $this->when($request->user()?->role === 'admin', $this->cost_price),
            'stock_quantity' => $this->stock_quantity,
            'sku' => $this->sku,
            'barcode' => $this->barcode,
            'weight' => $this->weight,
            'dimensions' => $this->dimensions,
            'notes' => $this->notes,
            'concentration' => $this->concentration,
            'volume_ml' => $this->volume_ml,
            'country_of_origin' => $this->country_of_origin,
            'brand' => $this->brand,
            'perfumer' => $this->perfumer,
            'release_year' => $this->release_year,
            'gender' => $this->gender,
            'is_active' => $this->is_active,
            'is_featured' => $this->is_featured,
            'is_new' => $this->is_new,
            'rating_average' => $this->rating_average,
            'rating_count' => $this->rating_count,
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
            // Nested relationships
            'category' => new CategoryResource($this->whenLoaded('category')),
            'images' => ProductImageResource::collection($this->whenLoaded('images')),
            'variants' => ProductVariantResource::collection($this->whenLoaded('variants')),
            'reviews' => ReviewResource::collection($this->whenLoaded('reviews')),
        ];
    }
}
