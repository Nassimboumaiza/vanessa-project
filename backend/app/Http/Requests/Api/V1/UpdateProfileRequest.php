<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;

class UpdateProfileRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'first_name' => ['nullable', 'string', 'max:100'],
            'last_name' => ['nullable', 'string', 'max:100'],
            'phone' => ['nullable', 'string', 'max:20'],
            'gender' => ['nullable', 'in:male,female,other'],
            'birthdate' => ['nullable', 'date', 'before:today'],
            'preferred_language' => ['nullable', 'string', 'max:10'],
            'preferred_currency' => ['nullable', 'string', 'max:3'],
        ];
    }

    public function messages(): array
    {
        return [
            'first_name.max' => 'First name cannot exceed 100 characters.',
            'last_name.max' => 'Last name cannot exceed 100 characters.',
            'phone.max' => 'Phone number cannot exceed 20 characters.',
            'gender.in' => 'Gender must be male, female, or other.',
            'birthdate.before' => 'Birthdate must be in the past.',
            'preferred_language.max' => 'Preferred language code cannot exceed 10 characters.',
            'preferred_currency.max' => 'Currency code cannot exceed 3 characters.',
        ];
    }
}
