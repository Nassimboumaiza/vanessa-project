<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;

class UpdateSettingsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'site_name' => ['nullable', 'string', 'max:255'],
            'site_description' => ['nullable', 'string', 'max:1000'],
            'contact_email' => ['nullable', 'email'],
            'support_phone' => ['nullable', 'string', 'max:20'],
            'currency' => ['nullable', 'string', 'size:3'],
            'currency_symbol' => ['nullable', 'string', 'max:5'],
            'tax_rate' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'free_shipping_threshold' => ['nullable', 'numeric', 'min:0'],
            'shipping_cost' => ['nullable', 'numeric', 'min:0'],
            'meta_title' => ['nullable', 'string', 'max:255'],
            'meta_description' => ['nullable', 'string', 'max:500'],
            'social_facebook' => ['nullable', 'url', 'max:255'],
            'social_instagram' => ['nullable', 'url', 'max:255'],
            'social_twitter' => ['nullable', 'url', 'max:255'],
            'maintenance_mode' => ['boolean'],
            'enable_reviews' => ['boolean'],
            'require_review_approval' => ['boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'site_name.max' => 'Site name cannot exceed 255 characters.',
            'site_description.max' => 'Site description cannot exceed 1000 characters.',
            'contact_email.email' => 'Please enter a valid email address.',
            'support_phone.max' => 'Support phone cannot exceed 20 characters.',
            'currency.size' => 'Currency code must be exactly 3 characters.',
            'currency_symbol.max' => 'Currency symbol cannot exceed 5 characters.',
            'tax_rate.min' => 'Tax rate cannot be negative.',
            'tax_rate.max' => 'Tax rate cannot exceed 100%.',
            'free_shipping_threshold.min' => 'Free shipping threshold cannot be negative.',
            'shipping_cost.min' => 'Shipping cost cannot be negative.',
            'meta_title.max' => 'Meta title cannot exceed 255 characters.',
            'meta_description.max' => 'Meta description cannot exceed 500 characters.',
            'social_facebook.url' => 'Please enter a valid Facebook URL.',
            'social_instagram.url' => 'Please enter a valid Instagram URL.',
            'social_twitter.url' => 'Please enter a valid Twitter URL.',
        ];
    }
}
