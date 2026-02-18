<?php

declare(strict_types=1);

namespace Tests\Unit\Http\Requests\Api\V1;

use App\Http\Requests\Api\V1\RegisterRequest;
use Illuminate\Support\Facades\Validator;
use Tests\TestCase;

/**
 * Unit tests for the RegisterRequest Form Request.
 *
 * Tests validation rules and custom error messages.
 */
class RegisterRequestTest extends TestCase
{
    /**
     * @test
     * Request passes with valid data.
     */
    public function request_passes_with_valid_data(): void
    {
        // Arrange
        $data = [
            'first_name' => 'John',
            'last_name' => 'Doe',
            'email' => 'john@example.com',
            'password' => 'SecurePassword123!',
            'password_confirmation' => 'SecurePassword123!',
            'phone' => '+1234567890',
        ];

        // Act
        $validator = Validator::make($data, (new RegisterRequest)->rules());

        // Assert
        $this->assertFalse($validator->fails());
    }

    /**
     * @test
     * Request fails without first name.
     */
    public function request_fails_without_first_name(): void
    {
        // Arrange
        $data = [
            'last_name' => 'Doe',
            'email' => 'john@example.com',
            'password' => 'SecurePassword123!',
            'password_confirmation' => 'SecurePassword123!',
        ];

        // Act
        $validator = Validator::make($data, (new RegisterRequest)->rules());

        // Assert
        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey('first_name', $validator->errors()->toArray());
    }

    /**
     * @test
     * Request fails with first name too long.
     */
    public function request_fails_with_first_name_too_long(): void
    {
        // Arrange
        $data = [
            'first_name' => str_repeat('a', 101),
            'last_name' => 'Doe',
            'email' => 'john@example.com',
            'password' => 'SecurePassword123!',
            'password_confirmation' => 'SecurePassword123!',
        ];

        // Act
        $validator = Validator::make($data, (new RegisterRequest)->rules());

        // Assert
        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey('first_name', $validator->errors()->toArray());
    }

    /**
     * @test
     * Request fails without last name.
     */
    public function request_fails_without_last_name(): void
    {
        // Arrange
        $data = [
            'first_name' => 'John',
            'email' => 'john@example.com',
            'password' => 'SecurePassword123!',
            'password_confirmation' => 'SecurePassword123!',
        ];

        // Act
        $validator = Validator::make($data, (new RegisterRequest)->rules());

        // Assert
        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey('last_name', $validator->errors()->toArray());
    }

    /**
     * @test
     * Request fails with last name too long.
     */
    public function request_fails_with_last_name_too_long(): void
    {
        // Arrange
        $data = [
            'first_name' => 'John',
            'last_name' => str_repeat('a', 101),
            'email' => 'john@example.com',
            'password' => 'SecurePassword123!',
            'password_confirmation' => 'SecurePassword123!',
        ];

        // Act
        $validator = Validator::make($data, (new RegisterRequest)->rules());

        // Assert
        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey('last_name', $validator->errors()->toArray());
    }

    /**
     * @test
     * Request fails without email.
     */
    public function request_fails_without_email(): void
    {
        // Arrange
        $data = [
            'first_name' => 'John',
            'last_name' => 'Doe',
            'password' => 'SecurePassword123!',
            'password_confirmation' => 'SecurePassword123!',
        ];

        // Act
        $validator = Validator::make($data, (new RegisterRequest)->rules());

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
            'first_name' => 'John',
            'last_name' => 'Doe',
            'email' => 'invalid-email',
            'password' => 'SecurePassword123!',
            'password_confirmation' => 'SecurePassword123!',
        ];

        // Act
        $validator = Validator::make($data, (new RegisterRequest)->rules());

        // Assert
        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey('email', $validator->errors()->toArray());
    }

    /**
     * @test
     * Request fails with duplicate email.
     */
    public function request_fails_with_duplicate_email(): void
    {
        // Arrange
        $existingUser = \App\Models\User::factory()->create();
        $data = [
            'first_name' => 'John',
            'last_name' => 'Doe',
            'email' => $existingUser->email,
            'password' => 'SecurePassword123!',
            'password_confirmation' => 'SecurePassword123!',
        ];

        // Act
        $validator = Validator::make($data, (new RegisterRequest)->rules());

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
            'first_name' => 'John',
            'last_name' => 'Doe',
            'email' => 'john@example.com',
        ];

        // Act
        $validator = Validator::make($data, (new RegisterRequest)->rules());

        // Assert
        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey('password', $validator->errors()->toArray());
    }

    /**
     * @test
     * Request fails with password too short.
     */
    public function request_fails_with_password_too_short(): void
    {
        // Arrange
        $data = [
            'first_name' => 'John',
            'last_name' => 'Doe',
            'email' => 'john@example.com',
            'password' => 'short',
            'password_confirmation' => 'short',
        ];

        // Act
        $validator = Validator::make($data, (new RegisterRequest)->rules());

        // Assert
        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey('password', $validator->errors()->toArray());
    }

    /**
     * @test
     * Request fails with mismatched password confirmation.
     */
    public function request_fails_with_mismatched_password_confirmation(): void
    {
        // Arrange
        $data = [
            'first_name' => 'John',
            'last_name' => 'Doe',
            'email' => 'john@example.com',
            'password' => 'SecurePassword123!',
            'password_confirmation' => 'DifferentPassword123!',
        ];

        // Act
        $validator = Validator::make($data, (new RegisterRequest)->rules());

        // Assert
        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey('password', $validator->errors()->toArray());
    }

    /**
     * @test
     * Request passes without phone (optional field).
     */
    public function request_passes_without_phone(): void
    {
        // Arrange
        $data = [
            'first_name' => 'John',
            'last_name' => 'Doe',
            'email' => 'john@example.com',
            'password' => 'SecurePassword123!',
            'password_confirmation' => 'SecurePassword123!',
        ];

        // Act
        $validator = Validator::make($data, (new RegisterRequest)->rules());

        // Assert
        $this->assertFalse($validator->fails());
    }

    /**
     * @test
     * Request passes with phone (optional field).
     */
    public function request_passes_with_phone(): void
    {
        // Arrange
        $data = [
            'first_name' => 'John',
            'last_name' => 'Doe',
            'email' => 'john@example.com',
            'password' => 'SecurePassword123!',
            'password_confirmation' => 'SecurePassword123!',
            'phone' => '+1234567890',
        ];

        // Act
        $validator = Validator::make($data, (new RegisterRequest)->rules());

        // Assert
        $this->assertFalse($validator->fails());
    }

    /**
     * @test
     * Custom error messages are returned for validation failures.
     */
    public function custom_error_messages_are_returned(): void
    {
        // Arrange
        $request = new RegisterRequest;
        $messages = $request->messages();

        // Assert
        $this->assertArrayHasKey('first_name.required', $messages);
        $this->assertArrayHasKey('email.required', $messages);
        $this->assertArrayHasKey('email.email', $messages);
        $this->assertArrayHasKey('password.required', $messages);
        $this->assertArrayHasKey('password.confirmed', $messages);
    }

    /**
     * @test
     * Request is authorized for all users.
     */
    public function request_is_authorized_for_all_users(): void
    {
        // Arrange
        $request = new RegisterRequest;

        // Assert
        $this->assertTrue($request->authorize());
    }
}
