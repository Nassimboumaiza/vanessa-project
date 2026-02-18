<?php

declare(strict_types=1);

namespace Tests\Feature\Api\V1;

use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Tests\TestCase;

/**
 * Feature tests for the Authentication Controller.
 *
 * Covers all authentication flows:
 * - User registration
 * - User login
 * - Token refresh
 * - User logout
 * - Password reset
 */
class AuthControllerTest extends TestCase
{
    /**
     * @test
     * A user can register with valid data.
     */
    public function a_user_can_register_with_valid_data(): void
    {
        // Arrange
        $registrationData = $this->getValidRegistrationData();

        // Act
        $response = $this->postJson($this->apiUrl('auth/register'), $registrationData);

        // Assert
        $this->assertSuccessfulResponse($response, 'User registered successfully', 201);
        $response->assertJsonStructure([
            'data' => [
                'user' => [
                    'id',
                    'name',
                    'email',
                    'role',
                ],
                'token',
            ],
        ]);

        $this->assertDatabaseHas('users', [
            'email' => $registrationData['email'],
            'role' => 'customer',
        ]);

        $user = User::where('email', $registrationData['email'])->first();
        $this->assertTokenExistsForUser($user);
    }

    /**
     * @test
     * Registration requires all mandatory fields.
     */
    public function registration_requires_all_mandatory_fields(): void
    {
        // Arrange
        $registrationData = [];

        // Act
        $response = $this->postJson($this->apiUrl('auth/register'), $registrationData);

        // Assert
        $this->assertValidationErrors($response, [
            'first_name',
            'last_name',
            'email',
            'password',
        ]);
    }

    /**
     * @test
     * Registration requires a valid email address.
     */
    public function registration_requires_a_valid_email_address(): void
    {
        // Arrange
        $registrationData = $this->getValidRegistrationData();
        $registrationData['email'] = 'invalid-email';

        // Act
        $response = $this->postJson($this->apiUrl('auth/register'), $registrationData);

        // Assert
        $this->assertValidationErrors($response, ['email']);
    }

    /**
     * @test
     * Registration requires a unique email address.
     */
    public function registration_requires_a_unique_email_address(): void
    {
        // Arrange
        $existingUser = User::factory()->create();
        $registrationData = $this->getValidRegistrationData();
        $registrationData['email'] = $existingUser->email;

        // Act
        $response = $this->postJson($this->apiUrl('auth/register'), $registrationData);

        // Assert
        $this->assertValidationErrors($response, ['email']);
    }

    /**
     * @test
     * Registration requires password confirmation to match.
     */
    public function registration_requires_password_confirmation_to_match(): void
    {
        // Arrange
        $registrationData = $this->getValidRegistrationData();
        $registrationData['password_confirmation'] = 'DifferentPassword123!';

        // Act
        $response = $this->postJson($this->apiUrl('auth/register'), $registrationData);

        // Assert
        $this->assertValidationErrors($response, ['password']);
    }

    /**
     * @test
     * Registration requires password minimum length.
     */
    public function registration_requires_password_minimum_length(): void
    {
        // Arrange
        $registrationData = $this->getValidRegistrationData();
        $registrationData['password'] = 'short';
        $registrationData['password_confirmation'] = 'short';

        // Act
        $response = $this->postJson($this->apiUrl('auth/register'), $registrationData);

        // Assert
        $this->assertValidationErrors($response, ['password']);
    }

    /**
     * @test
     * Registration concatenates first and last name into full name.
     */
    public function registration_concatenates_first_and_last_name_into_full_name(): void
    {
        // Arrange
        $registrationData = $this->getValidRegistrationData();

        // Act
        $response = $this->postJson($this->apiUrl('auth/register'), $registrationData);

        // Assert
        $this->assertSuccessfulResponse($response, 'User registered successfully', 201);
        $this->assertDatabaseHas('users', [
            'name' => $registrationData['first_name'] . ' ' . $registrationData['last_name'],
        ]);
    }

    /**
     * @test
     * Registration phone number is optional.
     */
    public function registration_phone_number_is_optional(): void
    {
        // Arrange
        $registrationData = $this->getValidRegistrationData();
        unset($registrationData['phone']);

        // Act
        $response = $this->postJson($this->apiUrl('auth/register'), $registrationData);

        // Assert
        $this->assertSuccessfulResponse($response, 'User registered successfully', 201);
        $this->assertDatabaseHas('users', [
            'email' => $registrationData['email'],
            'phone' => null,
        ]);
    }

    // ==========================================
    // LOGIN TESTS
    // ==========================================

    /**
     * @test
     * A user can login with valid credentials.
     */
    public function a_user_can_login_with_valid_credentials(): void
    {
        // Arrange
        $password = 'SecurePassword123!';
        $user = User::factory()->withPassword($password)->create([
            'email' => 'test@example.com',
        ]);

        $loginData = [
            'email' => $user->email,
            'password' => $password,
        ];

        // Act
        $response = $this->postJson($this->apiUrl('auth/login'), $loginData);

        // Assert
        $this->assertSuccessfulResponse($response, 'Login successful');
        $response->assertJsonStructure([
            'data' => [
                'user' => [
                    'id',
                    'name',
                    'email',
                ],
                'token',
            ],
        ]);

        $this->assertTokenExistsForUser($user);
        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'last_login_at' => now()->toDateTimeString(),
        ]);
    }

    /**
     * @test
     * Login fails with invalid credentials.
     */
    public function login_fails_with_invalid_credentials(): void
    {
        // Arrange
        $user = User::factory()->create([
            'email' => 'test@example.com',
        ]);

        $loginData = [
            'email' => $user->email,
            'password' => 'wrong-password',
        ];

        // Act
        $response = $this->postJson($this->apiUrl('auth/login'), $loginData);

        // Assert
        $this->assertErrorResponse($response, 'Invalid credentials', 401);
    }

    /**
     * @test
     * Login fails for non-existent user.
     */
    public function login_fails_for_non_existent_user(): void
    {
        // Arrange
        $loginData = [
            'email' => 'nonexistent@example.com',
            'password' => 'password',
        ];

        // Act
        $response = $this->postJson($this->apiUrl('auth/login'), $loginData);

        // Assert
        $this->assertErrorResponse($response, 'Invalid credentials', 401);
    }

    /**
     * @test
     * Login requires email field.
     */
    public function login_requires_email_field(): void
    {
        // Arrange
        $loginData = [
            'password' => 'password',
        ];

        // Act
        $response = $this->postJson($this->apiUrl('auth/login'), $loginData);

        // Assert
        $this->assertValidationErrors($response, ['email']);
    }

    /**
     * @test
     * Login requires password field.
     */
    public function login_requires_password_field(): void
    {
        // Arrange
        $loginData = [
            'email' => 'test@example.com',
        ];

        // Act
        $response = $this->postJson($this->apiUrl('auth/login'), $loginData);

        // Assert
        $this->assertValidationErrors($response, ['password']);
    }

    /**
     * @test
     * Login fails for inactive user.
     */
    public function login_fails_for_inactive_user(): void
    {
        // Arrange
        $password = 'SecurePassword123!';
        $user = User::factory()->withPassword($password)->inactive()->create();

        $loginData = [
            'email' => $user->email,
            'password' => $password,
        ];

        // Act
        $response = $this->postJson($this->apiUrl('auth/login'), $loginData);

        // Assert
        $this->assertErrorResponse($response, 'Account is disabled', 403);
    }

    /**
     * @test
     * Login supports remember me functionality.
     */
    public function login_supports_remember_me_functionality(): void
    {
        // Arrange
        $password = 'SecurePassword123!';
        $user = User::factory()->withPassword($password)->create();

        $loginData = [
            'email' => $user->email,
            'password' => $password,
            'remember' => true,
        ];

        // Act
        $response = $this->postJson($this->apiUrl('auth/login'), $loginData);

        // Assert
        $this->assertSuccessfulResponse($response, 'Login successful');
    }

    // ==========================================
    // LOGOUT TESTS
    // ==========================================

    /**
     * @test
     * Authenticated user can logout.
     */
    public function authenticated_user_can_logout(): void
    {
        // Arrange
        $user = User::factory()->create();
        $this->actingAsUser($user);

        // Act
        $response = $this->postJson($this->apiUrl('auth/logout'));

        // Assert
        $this->assertSuccessfulResponse($response, 'Logout successful');
        $this->assertNoTokensForUser($user);
    }

    /**
     * @test
     * Unauthenticated user cannot logout.
     */
    public function unauthenticated_user_cannot_logout(): void
    {
        // Act
        $response = $this->postJson($this->apiUrl('auth/logout'));

        // Assert
        $response->assertStatus(401);
    }

    // ==========================================
    // TOKEN REFRESH TESTS
    // ==========================================

    /**
     * @test
     * Authenticated user can refresh their token.
     */
    public function authenticated_user_can_refresh_their_token(): void
    {
        // Arrange
        $user = User::factory()->create();
        $oldToken = $this->actingAsUser($user)->get('Authorization');

        // Act
        $response = $this->postJson($this->apiUrl('auth/refresh'));

        // Assert
        $this->assertSuccessfulResponse($response, 'Token refreshed successfully');
        $response->assertJsonStructure([
            'data' => [
                'user',
                'token',
            ],
        ]);

        // Old tokens should be deleted
        $this->assertEquals(1, $user->tokens()->count());
    }

    /**
     * @test
     * Unauthenticated user cannot refresh token.
     */
    public function unauthenticated_user_cannot_refresh_token(): void
    {
        // Act
        $response = $this->postJson($this->apiUrl('auth/refresh'));

        // Assert
        $response->assertStatus(401);
    }

    // ==========================================
    // PASSWORD RESET TESTS
    // ==========================================

    /**
     * @test
     * User can request password reset link.
     */
    public function user_can_request_password_reset_link(): void
    {
        // Arrange
        $user = User::factory()->create([
            'email' => 'reset@example.com',
        ]);

        // Act
        $response = $this->postJson($this->apiUrl('auth/forgot-password'), [
            'email' => $user->email,
        ]);

        // Assert
        $this->assertSuccessfulResponse($response, 'Password reset link sent to your email');
    }

    /**
     * @test
     * Password reset requires valid email.
     */
    public function password_reset_requires_valid_email(): void
    {
        // Arrange
        $resetData = [
            'email' => 'invalid-email',
        ];

        // Act
        $response = $this->postJson($this->apiUrl('auth/forgot-password'), $resetData);

        // Assert
        $this->assertValidationErrors($response, ['email']);
    }

    /**
     * @test
     * Password reset request for non-existent email returns error.
     */
    public function password_reset_request_for_nonexistent_email_returns_error(): void
    {
        // Arrange
        $resetData = [
            'email' => 'nonexistent@example.com',
        ];

        // Act
        $response = $this->postJson($this->apiUrl('auth/forgot-password'), $resetData);

        // Assert - Laravel returns error for invalid email
        $this->assertErrorResponse($response, 'Unable to send password reset link', 400);
    }

    /**
     * @test
     * User can reset password with valid token.
     */
    public function user_can_reset_password_with_valid_token(): void
    {
        // Arrange
        $user = User::factory()->create([
            'email' => 'reset@example.com',
        ]);

        $token = Password::createToken($user);

        $resetData = [
            'token' => $token,
            'email' => $user->email,
            'password' => 'NewPassword123!',
            'password_confirmation' => 'NewPassword123!',
        ];

        // Act
        $response = $this->postJson($this->apiUrl('auth/reset-password'), $resetData);

        // Assert
        $this->assertSuccessfulResponse($response, 'Password has been reset successfully');
        $this->assertNoTokensForUser($user);
    }

    /**
     * @test
     * Password reset fails with invalid token.
     */
    public function password_reset_fails_with_invalid_token(): void
    {
        // Arrange
        $user = User::factory()->create([
            'email' => 'reset@example.com',
        ]);

        $resetData = [
            'token' => 'invalid-token',
            'email' => $user->email,
            'password' => 'NewPassword123!',
            'password_confirmation' => 'NewPassword123!',
        ];

        // Act
        $response = $this->postJson($this->apiUrl('auth/reset-password'), $resetData);

        // Assert
        $this->assertErrorResponse($response, 'Invalid token or email', 400);
    }

    /**
     * @test
     * Password reset requires password confirmation.
     */
    public function password_reset_requires_password_confirmation(): void
    {
        // Arrange
        $resetData = [
            'token' => 'some-token',
            'email' => 'test@example.com',
            'password' => 'NewPassword123!',
            'password_confirmation' => 'DifferentPassword123!',
        ];

        // Act
        $response = $this->postJson($this->apiUrl('auth/reset-password'), $resetData);

        // Assert
        $this->assertValidationErrors($response, ['password']);
    }

    /**
     * @test
     * Password reset requires minimum password length.
     */
    public function password_reset_requires_minimum_password_length(): void
    {
        // Arrange
        $resetData = [
            'token' => 'some-token',
            'email' => 'test@example.com',
            'password' => 'short',
            'password_confirmation' => 'short',
        ];

        // Act
        $response = $this->postJson($this->apiUrl('auth/reset-password'), $resetData);

        // Assert
        $this->assertValidationErrors($response, ['password']);
    }
}
