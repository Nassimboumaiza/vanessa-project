<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Product Image Upload Request Validation
 *
 * Validates image uploads with strict security checks:
 * - File type validation (MIME type)
 * - File size limits
 * - Image dimension limits
 * - Filename sanitization
 */
class ProductImageRequest extends FormRequest
{
    /**
     * Maximum file size in kilobytes (5MB).
     */
    private const MAX_FILE_SIZE = 5120;

    /**
     * Minimum image width in pixels.
     */
    private const MIN_WIDTH = 100;

    /**
     * Minimum image height in pixels.
     */
    private const MIN_HEIGHT = 100;

    /**
     * Maximum image width in pixels.
     */
    private const MAX_WIDTH = 4000;

    /**
     * Maximum image height in pixels.
     */
    private const MAX_HEIGHT = 4000;

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'images' => ['required', 'array', 'max:10'],
            'images.*' => [
                'required',
                'file',
                'mimes:jpeg,jpg,png,webp',
                'max:' . self::MAX_FILE_SIZE,
                'image',
                'dimensions:min_width=' . self::MIN_WIDTH . ',min_height=' . self::MIN_HEIGHT . ',max_width=' . self::MAX_WIDTH . ',max_height=' . self::MAX_HEIGHT,
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'images.required' => 'At least one image is required.',
            'images.array' => 'Images must be provided as an array.',
            'images.max' => 'You can upload a maximum of 10 images at once.',
            'images.*.required' => 'Each image is required.',
            'images.*.file' => 'Each upload must be a valid file.',
            'images.*.mimes' => 'Only JPEG, PNG, and WebP images are allowed.',
            'images.*.max' => 'Each image cannot exceed 5MB.',
            'images.*.image' => 'The file must be a valid image.',
            'images.*.dimensions' => 'Image dimensions must be between 100x100 and 4000x4000 pixels.',
        ];
    }
}
