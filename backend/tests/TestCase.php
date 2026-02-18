<?php

declare(strict_types=1);

namespace Tests;

use App\Models\User;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;

abstract class TestCase extends BaseTestCase
{
    use RefreshDatabase;

    /**
     * Base API URL for version 1.
     */
    protected string $baseApiUrl = '/api/v1';

    /**
     * Create and authenticate a user, returning the user and token.
     *
     * @return array{user: User, token: string}
     */
    protected function createAuthenticatedUser(array $attributes = []): array
    {
        $user = User::factory()->create($attributes);
        $token = $user->createToken('test_token')->plainTextToken;

        return ['user' => $user, 'token' => $token];
    }

    /**
     * Authenticate a user and set the authorization header.
     */
    protected function actingAsUser(User $user): self
    {
        $token = $user->createToken('test_token')->plainTextToken;

        return $this->withHeader('Authorization', 'Bearer ' . $token);
    }

    /**
     * Get valid registration data for testing.
     *
     * @return array<string, mixed>
     */
    protected function getValidRegistrationData(): array
    {
        return [
            'first_name' => 'John',
            'last_name' => 'Doe',
            'email' => 'john.doe@example.com',
            'password' => 'SecurePassword123!',
            'password_confirmation' => 'SecurePassword123!',
            'phone' => '+1234567890',
        ];
    }

    /**
     * Get valid login credentials for testing.
     *
     * @return array<string, mixed>
     */
    protected function getValidLoginCredentials(string $password = 'password'): array
    {
        return [
            'email' => 'test@example.com',
            'password' => $password,
        ];
    }

    /**
     * Assert that the response has a successful structure with the expected message.
     */
    protected function assertSuccessfulResponse(
        $response,
        ?string $expectedMessage = null,
        int $expectedStatus = 200
    ): void {
        $response->assertStatus($expectedStatus)
            ->assertJsonStructure([
                'success',
                'message',
                'data',
            ])
            ->assertJson([
                'success' => true,
            ]);

        if ($expectedMessage !== null) {
            $response->assertJson([
                'message' => $expectedMessage,
            ]);
        }
    }

    /**
     * Assert that the response contains error structure with expected details.
     */
    protected function assertErrorResponse(
        $response,
        ?string $expectedMessage = null,
        int $expectedStatus = 422
    ): void {
        $response->assertStatus($expectedStatus)
            ->assertJsonStructure([
                'success',
                'message',
            ])
            ->assertJson([
                'success' => false,
            ]);

        if ($expectedMessage !== null) {
            $response->assertJson([
                'message' => $expectedMessage,
            ]);
        }
    }

    /**
     * Assert that the response contains validation errors for specified fields.
     *
     * @param array<string> $fields
     */
    protected function assertValidationErrors($response, array $fields): void
    {
        $response->assertStatus(422)
            ->assertJsonStructure([
                'message',
                'errors' => [],
            ]);

        foreach ($fields as $field) {
            $response->assertJsonValidationErrors($field);
        }
    }

    /**
     * Assert that a user exists in the database with given attributes.
     *
     * @param array<string, mixed> $attributes
     */
    protected function assertUserExistsInDatabase(array $attributes): void
    {
        $this->assertDatabaseHas('users', $attributes);
    }

    /**
     * Assert that a user does not exist in the database with given attributes.
     *
     * @param array<string, mixed> $attributes
     */
    protected function assertUserNotExistsInDatabase(array $attributes): void
    {
        $this->assertDatabaseMissing('users', $attributes);
    }

    /**
     * Assert that a token exists in the database for a given user.
     */
    protected function assertTokenExistsForUser(User $user): void
    {
        $this->assertDatabaseHas('personal_access_tokens', [
            'tokenable_id' => $user->id,
            'tokenable_type' => User::class,
        ]);
    }

    /**
     * Assert that no tokens exist for a given user.
     */
    protected function assertNoTokensForUser(User $user): void
    {
        $this->assertDatabaseMissing('personal_access_tokens', [
            'tokenable_id' => $user->id,
            'tokenable_type' => User::class,
        ]);
    }

    /**
     * Get the base API URL for version 1 endpoints.
     */
    protected function apiUrl(string $path): string
    {
        return "{$this->baseApiUrl}/{$path}";
    }
}
