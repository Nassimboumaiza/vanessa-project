<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;

class StoreAddressRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'label' => ['nullable', 'string', 'max:50'],
            'first_name' => ['required', 'string', 'max:100'],
            'last_name' => ['required', 'string', 'max:100'],
            'company' => ['nullable', 'string', 'max:100'],
            'address_line_1' => ['required', 'string', 'max:255'],
            'address_line_2' => ['nullable', 'string', 'max:255'],
            'city' => ['required', 'string', 'max:100'],
            'state' => ['required', 'string', 'max:100'],
            'postal_code' => ['required', 'string', 'max:20'],
            'country' => ['required', 'string', 'max:100'],
            'phone' => ['nullable', 'string', 'max:20'],
            'is_default' => ['boolean'],
            'type' => ['required', 'in:shipping,billing'],
        ];
    }

    public function messages(): array
    {
        return [
            'first_name.required' => 'First name is required.',
            'last_name.required' => 'Last name is required.',
            'address_line_1.required' => 'Address line 1 is required.',
            'city.required' => 'City is required.',
            'state.required' => 'State is required.',
            'postal_code.required' => 'Postal code is required.',
            'country.required' => 'Country is required.',
            'type.required' => 'Address type is required.',
            'type.in' => 'Address type must be shipping or billing.',
        ];
    }
}
