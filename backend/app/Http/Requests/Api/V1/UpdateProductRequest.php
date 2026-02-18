<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateProductRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $productId = $this->route('product');

        return [
            'name' => ['nullable', 'string', 'max:200'],
            'description' => ['nullable', 'string'],
            'short_description' => ['nullable', 'string', 'max:500'],
            'category_id' => ['nullable', 'integer', 'exists:categories,id'],
            'price' => ['nullable', 'numeric', 'min:0'],
            'compare_price' => ['nullable', 'numeric', 'min:0'],
            'cost_price' => ['nullable', 'numeric', 'min:0'],
            'stock_quantity' => ['nullable', 'integer', 'min:0'],
            'sku' => ['nullable', 'string', 'max:100', Rule::unique('products', 'sku')->ignore($productId)],
            'barcode' => ['nullable', 'string', 'max:100'],
            'weight' => ['nullable', 'numeric', 'min:0'],
            'dimensions' => ['nullable', 'array'],
            'notes' => ['nullable', 'array'],
            'concentration' => ['nullable', 'string', 'max:50'],
            'volume_ml' => ['nullable', 'integer', 'min:0'],
            'country_of_origin' => ['nullable', 'string', 'max:100'],
            'brand' => ['nullable', 'string', 'max:100'],
            'perfumer' => ['nullable', 'string', 'max:100'],
            'release_year' => ['nullable', 'integer', 'min:1900', 'max:' . (now()->year + 1)],
            'gender' => ['nullable', 'in:unisex,masculine,feminine'],
            'is_active' => ['boolean'],
            'is_featured' => ['boolean'],
            'is_new' => ['boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'name.max' => 'Product name cannot exceed 200 characters.',
            'category_id.exists' => 'Selected category does not exist.',
            'price.numeric' => 'Price must be a number.',
            'price.min' => 'Price cannot be negative.',
            'stock_quantity.integer' => 'Stock quantity must be a whole number.',
            'stock_quantity.min' => 'Stock quantity cannot be negative.',
            'sku.unique' => 'This SKU is already in use.',
            'gender.in' => 'Gender must be unisex, masculine, or feminine.',
        ];
    }
}
