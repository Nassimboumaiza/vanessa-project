<?php

declare(strict_types=1);

namespace Tests\Unit\Http\Requests\Api\V1;

use App\Http\Requests\Api\V1\StoreOrderRequest;
use Illuminate\Support\Facades\Validator;
use Tests\TestCase;

/**
 * Unit tests for the StoreOrderRequest Form Request.
 *
 * Tests validation rules, authorization logic, and custom error messages.
 */
class StoreOrderRequestTest extends TestCase
{
    /**
     * Get valid order data for testing.
     *
     * @return array<string, mixed>
     */
    private function getValidOrderData(): array
    {
        return [
            'shipping_address' => [
                'first_name' => 'John',
                'last_name' => 'Doe',
                'company' => 'Acme Inc',
                'address_line_1' => '123 Main Street',
                'address_line_2' => 'Apt 4B',
                'city' => 'New York',
                'state' => 'NY',
                'postal_code' => '10001',
                'country' => 'USA',
                'phone' => '+1234567890',
            ],
            'billing_address' => [
                'first_name' => 'John',
                'last_name' => 'Doe',
                'company' => 'Acme Inc',
                'address_line_1' => '123 Main Street',
                'address_line_2' => 'Apt 4B',
                'city' => 'New York',
                'state' => 'NY',
                'postal_code' => '10001',
                'country' => 'USA',
                'phone' => '+1234567890',
            ],
            'payment_method' => 'credit_card',
            'coupon_code' => 'SAVE10',
            'customer_notes' => 'Please gift wrap this order',
        ];
    }

    // ==========================================
    // AUTHORIZATION TESTS
    // ==========================================

    /**
     * @test
     * Request is authorized for all authenticated users.
     */
    public function request_is_authorized_for_all_users(): void
    {
        // Arrange
        $request = new StoreOrderRequest;

        // Assert
        $this->assertTrue($request->authorize());
    }

    // ==========================================
    // SHIPPING ADDRESS VALIDATION TESTS
    // ==========================================

    /**
     * @test
     * Request passes with valid shipping address.
     */
    public function request_passes_with_valid_shipping_address(): void
    {
        // Arrange
        $data = $this->getValidOrderData();

        // Act
        $validator = Validator::make($data, (new StoreOrderRequest)->rules());

        // Assert
        $this->assertFalse($validator->fails());
    }

    /**
     * @test
     * Shipping address is required.
     */
    public function shipping_address_is_required(): void
    {
        // Arrange
        $data = $this->getValidOrderData();
        unset($data['shipping_address']);

        // Act
        $validator = Validator::make($data, (new StoreOrderRequest)->rules());

        // Assert
        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey('shipping_address', $validator->errors()->toArray());
    }

    /**
     * @test
     * Shipping first name is required.
     */
    public function shipping_first_name_is_required(): void
    {
        // Arrange
        $data = $this->getValidOrderData();
        unset($data['shipping_address']['first_name']);

        // Act
        $validator = Validator::make($data, (new StoreOrderRequest)->rules());

        // Assert
        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey('shipping_address.first_name', $validator->errors()->toArray());
    }

    /**
     * @test
     * Shipping first name must be a string.
     */
    public function shipping_first_name_must_be_string(): void
    {
        // Arrange
        $data = $this->getValidOrderData();
        $data['shipping_address']['first_name'] = 12345;

        // Act
        $validator = Validator::make($data, (new StoreOrderRequest)->rules());

        // Assert
        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey('shipping_address.first_name', $validator->errors()->toArray());
    }

    /**
     * @test
     * Shipping first name must not exceed 100 characters.
     */
    public function shipping_first_name_must_not_exceed_100_characters(): void
    {
        // Arrange
        $data = $this->getValidOrderData();
        $data['shipping_address']['first_name'] = str_repeat('a', 101);

        // Act
        $validator = Validator::make($data, (new StoreOrderRequest)->rules());

        // Assert
        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey('shipping_address.first_name', $validator->errors()->toArray());
    }

    /**
     * @test
     * Shipping last name is required.
     */
    public function shipping_last_name_is_required(): void
    {
        // Arrange
        $data = $this->getValidOrderData();
        unset($data['shipping_address']['last_name']);

        // Act
        $validator = Validator::make($data, (new StoreOrderRequest)->rules());

        // Assert
        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey('shipping_address.last_name', $validator->errors()->toArray());
    }

    /**
     * @test
     * Shipping address line 1 is required.
     */
    public function shipping_address_line_1_is_required(): void
    {
        // Arrange
        $data = $this->getValidOrderData();
        unset($data['shipping_address']['address_line_1']);

        // Act
        $validator = Validator::make($data, (new StoreOrderRequest)->rules());

        // Assert
        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey('shipping_address.address_line_1', $validator->errors()->toArray());
    }

    /**
     * @test
     * Shipping city is required.
     */
    public function shipping_city_is_required(): void
    {
        // Arrange
        $data = $this->getValidOrderData();
        unset($data['shipping_address']['city']);

        // Act
        $validator = Validator::make($data, (new StoreOrderRequest)->rules());

        // Assert
        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey('shipping_address.city', $validator->errors()->toArray());
    }

    /**
     * @test
     * Shipping state is required.
     */
    public function shipping_state_is_required(): void
    {
        // Arrange
        $data = $this->getValidOrderData();
        unset($data['shipping_address']['state']);

        // Act
        $validator = Validator::make($data, (new StoreOrderRequest)->rules());

        // Assert
        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey('shipping_address.state', $validator->errors()->toArray());
    }

    /**
     * @test
     * Shipping postal code is required.
     */
    public function shipping_postal_code_is_required(): void
    {
        // Arrange
        $data = $this->getValidOrderData();
        unset($data['shipping_address']['postal_code']);

        // Act
        $validator = Validator::make($data, (new StoreOrderRequest)->rules());

        // Assert
        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey('shipping_address.postal_code', $validator->errors()->toArray());
    }

    /**
     * @test
     * Shipping country is required.
     */
    public function shipping_country_is_required(): void
    {
        // Arrange
        $data = $this->getValidOrderData();
        unset($data['shipping_address']['country']);

        // Act
        $validator = Validator::make($data, (new StoreOrderRequest)->rules());

        // Assert
        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey('shipping_address.country', $validator->errors()->toArray());
    }

    /**
     * @test
     * Shipping company is optional.
     */
    public function shipping_company_is_optional(): void
    {
        // Arrange
        $data = $this->getValidOrderData();
        unset($data['shipping_address']['company']);

        // Act
        $validator = Validator::make($data, (new StoreOrderRequest)->rules());

        // Assert
        $this->assertFalse($validator->fails());
    }

    /**
     * @test
     * Shipping address line 2 is optional.
     */
    public function shipping_address_line_2_is_optional(): void
    {
        // Arrange
        $data = $this->getValidOrderData();
        unset($data['shipping_address']['address_line_2']);

        // Act
        $validator = Validator::make($data, (new StoreOrderRequest)->rules());

        // Assert
        $this->assertFalse($validator->fails());
    }

    /**
     * @test
     * Shipping phone is optional.
     */
    public function shipping_phone_is_optional(): void
    {
        // Arrange
        $data = $this->getValidOrderData();
        unset($data['shipping_address']['phone']);

        // Act
        $validator = Validator::make($data, (new StoreOrderRequest)->rules());

        // Assert
        $this->assertFalse($validator->fails());
    }

    // ==========================================
    // BILLING ADDRESS VALIDATION TESTS
    // ==========================================

    /**
     * @test
     * Billing address is required.
     */
    public function billing_address_is_required(): void
    {
        // Arrange
        $data = $this->getValidOrderData();
        unset($data['billing_address']);

        // Act
        $validator = Validator::make($data, (new StoreOrderRequest)->rules());

        // Assert
        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey('billing_address', $validator->errors()->toArray());
    }

    /**
     * @test
     * Billing first name is required.
     */
    public function billing_first_name_is_required(): void
    {
        // Arrange
        $data = $this->getValidOrderData();
        unset($data['billing_address']['first_name']);

        // Act
        $validator = Validator::make($data, (new StoreOrderRequest)->rules());

        // Assert
        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey('billing_address.first_name', $validator->errors()->toArray());
    }

    /**
     * @test
     * Billing last name is required.
     */
    public function billing_last_name_is_required(): void
    {
        // Arrange
        $data = $this->getValidOrderData();
        unset($data['billing_address']['last_name']);

        // Act
        $validator = Validator::make($data, (new StoreOrderRequest)->rules());

        // Assert
        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey('billing_address.last_name', $validator->errors()->toArray());
    }

    /**
     * @test
     * Billing address line 1 is required.
     */
    public function billing_address_line_1_is_required(): void
    {
        // Arrange
        $data = $this->getValidOrderData();
        unset($data['billing_address']['address_line_1']);

        // Act
        $validator = Validator::make($data, (new StoreOrderRequest)->rules());

        // Assert
        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey('billing_address.address_line_1', $validator->errors()->toArray());
    }

    /**
     * @test
     * Billing city is required.
     */
    public function billing_city_is_required(): void
    {
        // Arrange
        $data = $this->getValidOrderData();
        unset($data['billing_address']['city']);

        // Act
        $validator = Validator::make($data, (new StoreOrderRequest)->rules());

        // Assert
        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey('billing_address.city', $validator->errors()->toArray());
    }

    /**
     * @test
     * Billing optional fields can be omitted.
     */
    public function billing_optional_fields_can_be_omitted(): void
    {
        // Arrange
        $data = $this->getValidOrderData();
        unset($data['billing_address']['company']);
        unset($data['billing_address']['address_line_2']);
        unset($data['billing_address']['phone']);

        // Act
        $validator = Validator::make($data, (new StoreOrderRequest)->rules());

        // Assert
        $this->assertFalse($validator->fails());
    }

    // ==========================================
    // PAYMENT METHOD VALIDATION TESTS
    // ==========================================

    /**
     * @test
     * Payment method is required.
     */
    public function payment_method_is_required(): void
    {
        // Arrange
        $data = $this->getValidOrderData();
        unset($data['payment_method']);

        // Act
        $validator = Validator::make($data, (new StoreOrderRequest)->rules());

        // Assert
        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey('payment_method', $validator->errors()->toArray());
    }

    /**
     * @test
     * Valid payment methods are accepted.
     */
    public function valid_payment_methods_are_accepted(): void
    {
        // Arrange
        $validMethods = ['credit_card', 'paypal', 'bank_transfer', 'cash_on_delivery'];

        foreach ($validMethods as $method) {
            $data = $this->getValidOrderData();
            $data['payment_method'] = $method;

            // Act
            $validator = Validator::make($data, (new StoreOrderRequest)->rules());

            // Assert
            $this->assertFalse($validator->fails(), "Payment method {$method} should be valid");
        }
    }

    /**
     * @test
     * Invalid payment method is rejected.
     */
    public function invalid_payment_method_is_rejected(): void
    {
        // Arrange
        $data = $this->getValidOrderData();
        $data['payment_method'] = 'bitcoin';

        // Act
        $validator = Validator::make($data, (new StoreOrderRequest)->rules());

        // Assert
        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey('payment_method', $validator->errors()->toArray());
    }

    // ==========================================
    // OPTIONAL FIELDS VALIDATION TESTS
    // ==========================================

    /**
     * @test
     * Coupon code is optional.
     */
    public function coupon_code_is_optional(): void
    {
        // Arrange
        $data = $this->getValidOrderData();
        unset($data['coupon_code']);

        // Act
        $validator = Validator::make($data, (new StoreOrderRequest)->rules());

        // Assert
        $this->assertFalse($validator->fails());
    }

    /**
     * @test
     * Coupon code has max length of 50 characters.
     */
    public function coupon_code_has_max_length_of_50(): void
    {
        // Arrange
        $data = $this->getValidOrderData();
        $data['coupon_code'] = str_repeat('A', 51);

        // Act
        $validator = Validator::make($data, (new StoreOrderRequest)->rules());

        // Assert
        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey('coupon_code', $validator->errors()->toArray());
    }

    /**
     * @test
     * Customer notes are optional.
     */
    public function customer_notes_are_optional(): void
    {
        // Arrange
        $data = $this->getValidOrderData();
        unset($data['customer_notes']);

        // Act
        $validator = Validator::make($data, (new StoreOrderRequest)->rules());

        // Assert
        $this->assertFalse($validator->fails());
    }

    /**
     * @test
     * Customer notes has max length of 1000 characters.
     */
    public function customer_notes_has_max_length_of_1000(): void
    {
        // Arrange
        $data = $this->getValidOrderData();
        $data['customer_notes'] = str_repeat('a', 1001);

        // Act
        $validator = Validator::make($data, (new StoreOrderRequest)->rules());

        // Assert
        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey('customer_notes', $validator->errors()->toArray());
    }

    // ==========================================
    // CUSTOM ERROR MESSAGES TESTS
    // ==========================================

    /**
     * @test
     * Custom error messages are returned for validation failures.
     */
    public function custom_error_messages_are_returned(): void
    {
        // Arrange
        $request = new StoreOrderRequest;
        $messages = $request->messages();

        // Assert
        $this->assertArrayHasKey('shipping_address.required', $messages);
        $this->assertArrayHasKey('shipping_address.first_name.required', $messages);
        $this->assertArrayHasKey('shipping_address.last_name.required', $messages);
        $this->assertArrayHasKey('billing_address.required', $messages);
        $this->assertArrayHasKey('payment_method.required', $messages);
        $this->assertArrayHasKey('payment_method.in', $messages);

        // Verify message content
        $this->assertEquals('Shipping address is required.', $messages['shipping_address.required']);
        $this->assertEquals('Invalid payment method selected.', $messages['payment_method.in']);
    }

    // ==========================================
    // COMPLETE VALIDATION RULES TEST
    // ==========================================

    /**
     * @test
     * Request has correct validation rules structure.
     */
    public function request_has_correct_validation_rules(): void
    {
        // Arrange
        $request = new StoreOrderRequest;
        $rules = $request->rules();

        // Assert - Shipping address rules
        $this->assertArrayHasKey('shipping_address', $rules);
        $this->assertArrayHasKey('shipping_address.first_name', $rules);
        $this->assertArrayHasKey('shipping_address.last_name', $rules);
        $this->assertArrayHasKey('shipping_address.company', $rules);
        $this->assertArrayHasKey('shipping_address.address_line_1', $rules);
        $this->assertArrayHasKey('shipping_address.address_line_2', $rules);
        $this->assertArrayHasKey('shipping_address.city', $rules);
        $this->assertArrayHasKey('shipping_address.state', $rules);
        $this->assertArrayHasKey('shipping_address.postal_code', $rules);
        $this->assertArrayHasKey('shipping_address.country', $rules);
        $this->assertArrayHasKey('shipping_address.phone', $rules);

        // Assert - Billing address rules
        $this->assertArrayHasKey('billing_address', $rules);
        $this->assertArrayHasKey('billing_address.first_name', $rules);
        $this->assertArrayHasKey('billing_address.last_name', $rules);

        // Assert - Payment and other rules
        $this->assertArrayHasKey('payment_method', $rules);
        $this->assertArrayHasKey('coupon_code', $rules);
        $this->assertArrayHasKey('customer_notes', $rules);

        // Assert - Payment method in constraint
        $this->assertContains('in:credit_card,paypal,bank_transfer,cash_on_delivery', $rules['payment_method']);
    }
}
