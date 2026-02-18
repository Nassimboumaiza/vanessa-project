<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateCategoryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $categoryId = $this->route('category');

        return [
            'name' => ['nullable', 'string', 'max:100', Rule::unique('categories', 'name')->ignore($categoryId)],
            'description' => ['nullable', 'string'],
            'parent_id' => ['nullable', 'integer', 'exists:categories,id', 'not_in:' . $categoryId],
            'sort_order' => ['integer', 'min:0'],
            'is_active' => ['boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'name.unique' => 'This category name already exists.',
            'name.max' => 'Category name cannot exceed 100 characters.',
            'parent_id.exists' => 'The selected parent category does not exist.',
            'parent_id.not_in' => 'A category cannot be its own parent.',
        ];
    }
}
