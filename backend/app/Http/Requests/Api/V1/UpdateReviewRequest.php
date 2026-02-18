<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;

class UpdateReviewRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'rating' => ['nullable', 'integer', 'between:1,5'],
            'title' => ['nullable', 'string', 'max:200'],
            'content' => ['nullable', 'string', 'min:10', 'max:2000'],
            'pros' => ['nullable', 'array', 'max:5'],
            'pros.*' => ['string', 'max:100'],
            'cons' => ['nullable', 'array', 'max:5'],
            'cons.*' => ['string', 'max:100'],
        ];
    }

    public function messages(): array
    {
        return [
            'rating.between' => 'Rating must be between 1 and 5.',
            'content.min' => 'Review must be at least 10 characters long.',
            'content.max' => 'Review cannot exceed 2000 characters.',
            'pros.max' => 'You can only add up to 5 pros.',
            'pros.*.max' => 'Each pro cannot exceed 100 characters.',
            'cons.max' => 'You can only add up to 5 cons.',
            'cons.*.max' => 'Each con cannot exceed 100 characters.',
        ];
    }
}
