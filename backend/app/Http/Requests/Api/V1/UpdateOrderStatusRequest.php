<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;

class UpdateOrderStatusRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'status' => ['required', 'in:pending,processing,shipped,delivered,cancelled,refunded'],
            'notes' => ['nullable', 'string', 'max:1000'],
            'tracking_number' => ['nullable', 'string', 'max:100'],
            'carrier' => ['nullable', 'string', 'max:50'],
        ];
    }

    public function messages(): array
    {
        return [
            'status.required' => 'Order status is required.',
            'status.in' => 'Invalid status value.',
        ];
    }
}
