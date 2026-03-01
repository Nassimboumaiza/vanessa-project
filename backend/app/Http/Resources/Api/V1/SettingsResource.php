<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin array<string, mixed>
 */
class SettingsResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'site_name' => $this->resource['site_name'] ?? config('app.name', 'Vanessa Perfumes'),
            'site_description' => $this->resource['site_description'] ?? '',
            'contact_email' => $this->resource['contact_email'] ?? '',
            'support_phone' => $this->resource['support_phone'] ?? '',
            'currency' => $this->resource['currency'] ?? 'USD',
            'currency_symbol' => $this->resource['currency_symbol'] ?? '$',
            'tax_rate' => $this->resource['tax_rate'] ?? 0,
            'free_shipping_threshold' => $this->resource['free_shipping_threshold'] ?? 0,
            'shipping_cost' => $this->resource['shipping_cost'] ?? 0,
            'meta_title' => $this->resource['meta_title'] ?? '',
            'meta_description' => $this->resource['meta_description'] ?? '',
            'social_facebook' => $this->resource['social_facebook'] ?? '',
            'social_instagram' => $this->resource['social_instagram'] ?? '',
            'social_twitter' => $this->resource['social_twitter'] ?? '',
            'maintenance_mode' => $this->resource['maintenance_mode'] ?? false,
            'enable_reviews' => $this->resource['enable_reviews'] ?? true,
            'require_review_approval' => $this->resource['require_review_approval'] ?? true,
        ];
    }
}
