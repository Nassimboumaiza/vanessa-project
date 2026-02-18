<?php

declare(strict_types=1);

namespace Tests\Unit\Http\Requests\Api\V1;

use App\Http\Requests\Api\V1\LoginRequest;
use Illuminate\Support\Facades\Validator;
use Tests\TestCase;

/**
 * Unit tests for the LoginRequest Form Request.
 *
 * Tests validation rules and custom error messages.
 */
class LoginRequestTest extends TestCase
{
    /**
     * @test
     * Request passes with valid credentials.
     */
    public function request_passes_with_valid_credentials(): void
    {
        // Arrange
        $data = [
            'email' => 'user@example.com',
            'password' => 'password123',
        ];

        // Act
        $validator = Validator::make($data, (new LoginRequest())->rules());

        // Assert
        $this->assertFalse($validator->fails());
    }

    /**
     * @test
     * Request passes with remember me option.
     */
    public function request_passes_with_remember_me_option(): void
    {
        // Arrange
        $data = [
            'email' => 'user@example.com',
            'password' => 'password123',
            'remember' => true,
        ];

        // Act
        $validator = Validator::make($data, (new LoginRequest())->rules());

        // Assert
        $this->assertFalse($validator->fails());
    }

    /**
     * @test
     * Request fails without email.
     */
    public function request_fails_without_email(): void
    {
        // Arrange
        $data = [
            'password' => 'password123',
        ];

        // Act
        $validator = Validator::make($data, (new LoginRequest())->rules());

        // Assert
        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey('email', $validator->errors()->toArray());
    }

    /**
     * @test
     * Request fails with invalid email format.
     */
    public function request_fails_with_invalid_email_format(): void
    {
        // Arrange
        $data = [
            'email' => 'not-an-email',
            'password' => 'password123',
        ];

        // Act
        $validator = Validator::make($data, (new LoginRequest())->rules());

        // Assert
        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey('email', $validator->errors()->toArray());
    }

    /**
     * @test
     * Request fails without password.
     */
    public function request_fails_without_password(): void
    {
        // Arrange
        $data = [
            'email' => 'user@example.com',
        ];

        // Act
        $validator = Validator::make($data, (new LoginRequest())->rules());

        // Assert
        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey('password', $validator->errors()->toArray());
    }

    /**
     * @test
     * Request passes with empty data (validation happens but may fail).
     */
    public function request_has_correct_validation_rules(): void
    {
        // Arrange
        $request = new LoginRequest();
        $rules = $request->rules();

        // Assert
        $this->assertArrayHasKey('email', $rules);
        $this->assertArrayHasKey('password', $rules);
        $this->assertArrayHasKey('remember', $rules);

        $this->assertEquals(['required', 'string', 'email'], $rules['email']);
        $this->assertEquals(['required', 'string'], $rules['password']);
        $this->assertEquals(['boolean'], $rules['remember']);
    }

    /**
     * @test
     * Custom error messages are returned for validation failures.
     */
    public function custom_error_messages_are_returned(): void
    {
        // Arrange
        $request = new LoginRequest();
        $messages = $request->messages();

        // Assert
        $this->assertArrayHasKey('email.required', $messages);
        $this->assertArrayHasKey('email.email', $messages);
        $this->assertArrayHasKey('password.required', $messages);
    }

    /**
     * @test
     * Request is authorized for all users.
     */
    public function request_is_authorized_for_all_users(): void
    {
        // Arrange
        $request = new LoginRequest();

        // Assert
        $this->assertTrue($request->authorize());
    }
}
