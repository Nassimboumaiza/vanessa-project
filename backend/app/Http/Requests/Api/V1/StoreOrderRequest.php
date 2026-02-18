<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;

class StoreOrderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'shipping_address' => ['required', 'array'],
            'shipping_address.first_name' => ['required', 'string', 'max:100'],
            'shipping_address.last_name' => ['required', 'string', 'max:100'],
            'shipping_address.company' => ['nullable', 'string', 'max:100'],
            'shipping_address.address_line_1' => ['required', 'string', 'max:255'],
            'shipping_address.address_line_2' => ['nullable', 'string', 'max:255'],
            'shipping_address.city' => ['required', 'string', 'max:100'],
            'shipping_address.state' => ['required', 'string', 'max:100'],
            'shipping_address.postal_code' => ['required', 'string', 'max:20'],
            'shipping_address.country' => ['required', 'string', 'max:100'],
            'shipping_address.phone' => ['nullable', 'string', 'max:20'],
            'billing_address' => ['required', 'array'],
            'billing_address.first_name' => ['required', 'string', 'max:100'],
            'billing_address.last_name' => ['required', 'string', 'max:100'],
            'billing_address.company' => ['nullable', 'string', 'max:100'],
            'billing_address.address_line_1' => ['required', 'string', 'max:255'],
            'billing_address.address_line_2' => ['nullable', 'string', 'max:255'],
            'billing_address.city' => ['required', 'string', 'max:100'],
            'billing_address.state' => ['required', 'string', 'max:100'],
            'billing_address.postal_code' => ['required', 'string', 'max:20'],
            'billing_address.country' => ['required', 'string', 'max:100'],
            'billing_address.phone' => ['nullable', 'string', 'max:20'],
            'payment_method' => ['required', 'string', 'in:credit_card,paypal,bank_transfer,cash_on_delivery'],
            'coupon_code' => ['nullable', 'string', 'max:50'],
            'customer_notes' => ['nullable', 'string', 'max:1000'],
        ];
    }

    public function messages(): array
    {
        return [
            'shipping_address.required' => 'Shipping address is required.',
            'shipping_address.first_name.required' => 'Shipping first name is required.',
            'shipping_address.last_name.required' => 'Shipping last name is required.',
            'shipping_address.address_line_1.required' => 'Shipping address line 1 is required.',
            'shipping_address.city.required' => 'Shipping city is required.',
            'shipping_address.state.required' => 'Shipping state is required.',
            'shipping_address.postal_code.required' => 'Shipping postal code is required.',
            'shipping_address.country.required' => 'Shipping country is required.',
            'billing_address.required' => 'Billing address is required.',
            'billing_address.first_name.required' => 'Billing first name is required.',
            'billing_address.last_name.required' => 'Billing last name is required.',
            'billing_address.address_line_1.required' => 'Billing address line 1 is required.',
            'billing_address.city.required' => 'Billing city is required.',
            'billing_address.state.required' => 'Billing state is required.',
            'billing_address.postal_code.required' => 'Billing postal code is required.',
            'billing_address.country.required' => 'Billing country is required.',
            'payment_method.required' => 'Payment method is required.',
            'payment_method.in' => 'Invalid payment method selected.',
        ];
    }
}
