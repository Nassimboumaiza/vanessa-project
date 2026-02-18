<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;

class UpdateAddressRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'label' => ['nullable', 'string', 'max:50'],
            'first_name' => ['nullable', 'string', 'max:100'],
            'last_name' => ['nullable', 'string', 'max:100'],
            'company' => ['nullable', 'string', 'max:100'],
            'address_line_1' => ['nullable', 'string', 'max:255'],
            'address_line_2' => ['nullable', 'string', 'max:255'],
            'city' => ['nullable', 'string', 'max:100'],
            'state' => ['nullable', 'string', 'max:100'],
            'postal_code' => ['nullable', 'string', 'max:20'],
            'country' => ['nullable', 'string', 'max:100'],
            'phone' => ['nullable', 'string', 'max:20'],
            'is_default' => ['boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'label.max' => 'Label cannot exceed 50 characters.',
            'first_name.max' => 'First name cannot exceed 100 characters.',
            'last_name.max' => 'Last name cannot exceed 100 characters.',
            'address_line_1.max' => 'Address cannot exceed 255 characters.',
            'city.max' => 'City cannot exceed 100 characters.',
            'state.max' => 'State cannot exceed 100 characters.',
            'postal_code.max' => 'Postal code cannot exceed 20 characters.',
            'country.max' => 'Country cannot exceed 100 characters.',
            'phone.max' => 'Phone cannot exceed 20 characters.',
        ];
    }
}
