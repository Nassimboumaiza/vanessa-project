<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Product Search Request Validation
 *
 * Validates search queries with length limits and pagination bounds
 * to prevent DoS attacks and ensure consistent API behavior.
 */
class ProductSearchRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'q' => ['required', 'string', 'min:2', 'max:100'],
            'category' => ['nullable', 'string', 'max:100'],
            'min_price' => ['nullable', 'numeric', 'min:0', 'max:999999.99'],
            'max_price' => ['nullable', 'numeric', 'min:0', 'max:999999.99', 'gte:min_price'],
            'sort_by' => ['nullable', 'string', 'in:name,price,created_at,rating,popularity'],
            'sort_order' => ['nullable', 'string', 'in:asc,desc'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:50'],
            'page' => ['nullable', 'integer', 'min:1'],
        ];
    }

    public function messages(): array
    {
        return [
            'q.required' => 'Search query is required.',
            'q.min' => 'Search query must be at least 2 characters.',
            'q.max' => 'Search query cannot exceed 100 characters.',
            'min_price.min' => 'Minimum price cannot be negative.',
            'max_price.gte' => 'Maximum price must be greater than or equal to minimum price.',
            'sort_by.in' => 'Invalid sort field.',
            'sort_order.in' => 'Sort order must be asc or desc.',
            'per_page.max' => 'Maximum 50 items per page allowed.',
        ];
    }
}
