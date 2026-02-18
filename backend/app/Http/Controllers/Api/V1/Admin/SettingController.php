<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Api\BaseController;
use App\Http\Requests\Api\V1\UpdateSettingsRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;

class SettingController extends BaseController
{
    private array $allowedSettings = [
        'site_name',
        'site_description',
        'contact_email',
        'support_phone',
        'currency',
        'currency_symbol',
        'tax_rate',
        'free_shipping_threshold',
        'shipping_cost',
        'meta_title',
        'meta_description',
        'social_facebook',
        'social_instagram',
        'social_twitter',
        'maintenance_mode',
        'enable_reviews',
        'require_review_approval',
    ];

    /**
     * Get all settings.
     */
    public function index(): JsonResponse
    {
        $settings = Cache::remember('site_settings', 3600, function () {
            return $this->getDefaultSettings();
        });

        return $this->successResponse($settings, 'Settings retrieved successfully');
    }

    /**
     * Update settings.
     */
    public function update(UpdateSettingsRequest $request): JsonResponse
    {
        $validated = $request->validated();

        // TODO: Save to database or config file
        // For now, clear cache to simulate update
        Cache::forget('site_settings');

        return $this->successResponse($validated, 'Settings updated successfully');
    }

    /**
     * Get default settings.
     */
    private function getDefaultSettings(): array
    {
        return [
            'site_name' => config('app.name', 'Vanessa Perfumes'),
            'site_description' => 'Luxury fragrances crafted with the finest ingredients',
            'contact_email' => config('mail.from.address', 'contact@vanessaperfumes.com'),
            'support_phone' => '+1 (555) 123-4567',
            'currency' => 'USD',
            'currency_symbol' => '$',
            'tax_rate' => 10,
            'free_shipping_threshold' => 100,
            'shipping_cost' => 15,
            'meta_title' => 'Vanessa Perfumes | Luxury Fragrances',
            'meta_description' => 'Discover our exclusive collection of luxury perfumes',
            'social_facebook' => 'https://facebook.com/vanessaperfumes',
            'social_instagram' => 'https://instagram.com/vanessaperfumes',
            'social_twitter' => 'https://twitter.com/vanessaperfumes',
            'maintenance_mode' => false,
            'enable_reviews' => true,
            'require_review_approval' => true,
        ];
    }
}
