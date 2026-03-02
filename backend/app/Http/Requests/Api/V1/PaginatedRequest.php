<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Paginated Request Validation
 *
 * Provides consistent pagination validation across all endpoints.
 * Prevents DoS attacks via excessive per_page values.
 */
class PaginatedRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
            'page' => ['nullable', 'integer', 'min:1'],
            'sort_by' => ['nullable', 'string', 'max:50', 'alpha_dash'],
            'sort_order' => ['nullable', 'string', 'in:asc,desc'],
        ];
    }

    public function messages(): array
    {
        return [
            'per_page.integer' => 'Per page must be a valid number.',
            'per_page.min' => 'Per page must be at least 1.',
            'per_page.max' => 'Per page cannot exceed 100.',
            'page.integer' => 'Page must be a valid number.',
            'page.min' => 'Page must be at least 1.',
            'sort_by.max' => 'Sort field cannot exceed 50 characters.',
            'sort_by.alpha_dash' => 'Sort field contains invalid characters.',
            'sort_order.in' => 'Sort order must be asc or desc.',
        ];
    }

    /**
     * Get validated per_page value with default fallback.
     */
    public function getPerPage(int $default = 15): int
    {
        return (int) $this->validated()['per_page'] ?? $default;
    }

    /**
     * Get validated page value with default fallback.
     */
    public function getPage(): int
    {
        return (int) $this->validated()['page'] ?? 1;
    }
}
