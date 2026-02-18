<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;

class StoreProductRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:200'],
            'description' => ['nullable', 'string'],
            'short_description' => ['nullable', 'string', 'max:500'],
            'category_id' => ['required', 'integer', 'exists:categories,id'],
            'price' => ['required', 'numeric', 'min:0'],
            'compare_price' => ['nullable', 'numeric', 'min:0'],
            'cost_price' => ['nullable', 'numeric', 'min:0'],
            'stock_quantity' => ['required', 'integer', 'min:0'],
            'sku' => ['required', 'string', 'max:100', 'unique:products,sku'],
            'barcode' => ['nullable', 'string', 'max:100'],
            'weight' => ['nullable', 'numeric', 'min:0'],
            'dimensions' => ['nullable', 'array'],
            'notes' => ['nullable', 'array'],
            'concentration' => ['nullable', 'string', 'max:50'],
            'volume_ml' => ['nullable', 'integer', 'min:0'],
            'country_of_origin' => ['nullable', 'string', 'max:100'],
            'brand' => ['nullable', 'string', 'max:100'],
            'perfumer' => ['nullable', 'string', 'max:100'],
            'release_year' => ['nullable', 'integer', 'min:1900', 'max:'.(now()->year + 1)],
            'gender' => ['nullable', 'in:unisex,masculine,feminine'],
            'is_active' => ['boolean'],
            'is_featured' => ['boolean'],
            'is_new' => ['boolean'],
            'variants' => ['nullable', 'array'],
            'variants.*.name' => ['required_with:variants', 'string', 'max:100'],
            'variants.*.sku' => ['required_with:variants', 'string', 'max:100', 'distinct'],
            'variants.*.price' => ['required_with:variants', 'numeric', 'min:0'],
            'variants.*.stock_quantity' => ['required_with:variants', 'integer', 'min:0'],
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => 'Product name is required.',
            'name.max' => 'Product name cannot exceed 200 characters.',
            'category_id.required' => 'Category is required.',
            'category_id.exists' => 'Selected category does not exist.',
            'price.required' => 'Price is required.',
            'price.numeric' => 'Price must be a number.',
            'price.min' => 'Price cannot be negative.',
            'stock_quantity.required' => 'Stock quantity is required.',
            'stock_quantity.integer' => 'Stock quantity must be a whole number.',
            'stock_quantity.min' => 'Stock quantity cannot be negative.',
            'sku.required' => 'SKU is required.',
            'sku.unique' => 'This SKU is already in use.',
            'gender.in' => 'Gender must be unisex, masculine, or feminine.',
            'variants.*.name.required_with' => 'Variant name is required when adding variants.',
            'variants.*.sku.required_with' => 'Variant SKU is required when adding variants.',
            'variants.*.price.required_with' => 'Variant price is required when adding variants.',
            'variants.*.stock_quantity.required_with' => 'Variant stock quantity is required when adding variants.',
        ];
    }
}
