<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;

class AuthService
{
    public function __construct(
        private readonly UserService $userService
    ) {}

    /**
     * Authenticate user with credentials.
     *
     * @param array<string, mixed> $credentials
     * @return array<string, mixed>
     */
    public function attemptLogin(array $credentials): array
    {
        $email = strtolower(trim($credentials['email']));
        $password = $credentials['password'];
        $remember = $credentials['remember'] ?? false;

        if (! Auth::attempt(['email' => $email, 'password' => $password], $remember)) {
            return [
                'success' => false,
                'message' => 'Invalid credentials',
            ];
        }

        $user = Auth::user();

        if (! $user->is_active) {
            Auth::logout();

            return [
                'success' => false,
                'message' => 'Account is disabled',
                'status' => 403,
            ];
        }

        // Update last login
        $user->update(['last_login_at' => now()]);

        // Create Sanctum token for API
        $token = $user->createToken('api-token')->plainTextToken;

        return [
            'success' => true,
            'user' => $user,
            'token' => $token,
            'message' => 'Login successful',
        ];
    }

    /**
     * Logout user.
     */
    public function logout(User $user): void
    {
        // Delete all tokens for the user
        $user->tokens()->delete();
    }

    /**
     * Refresh user token.
     */
    public function refreshToken(User $user): string
    {
        // Delete current token
        $user->currentAccessToken()?->delete();

        // Create new token
        return $user->createToken('api-token')->plainTextToken;
    }

    /**
     * Handle forgot password request.
     */
    public function sendPasswordResetLink(string $email): array
    {
        $user = $this->userService->findByEmail($email);

        if (! $user) {
            // Don't reveal if email exists
            return [
                'success' => true,
                'message' => 'If your email exists in our system, you will receive a password reset link',
            ];
        }

        $token = $this->userService->generatePasswordResetToken($user);

        // Send email with token (implementation would go here)
        // Mail::to($user->email)->send(new PasswordResetMail($user, $token));

        return [
            'success' => true,
            'message' => 'Password reset link has been sent to your email',
        ];
    }

    /**
     * Validate password reset token.
     */
    public function validateResetToken(string $email, string $token): bool
    {
        $record = \DB::table('password_reset_tokens')
            ->where('email', strtolower(trim($email)))
            ->first();

        if (! $record) {
            return false;
        }

        // Check if token is valid (within 60 minutes)
        if (now()->diffInMinutes($record->created_at) > 60) {
            return false;
        }

        return Hash::check($token, $record->token);
    }

    /**
     * Reset user password.
     *
     * @param array<string, mixed> $data
     */
    public function resetPassword(array $data): array
    {
        $email = strtolower(trim($data['email']));
        $token = $data['token'];
        $password = $data['password'];

        if (! $this->validateResetToken($email, $token)) {
            return [
                'success' => false,
                'message' => 'Invalid or expired reset token',
            ];
        }

        $user = $this->userService->findByEmail($email);

        if (! $user) {
            return [
                'success' => false,
                'message' => 'User not found',
            ];
        }

        // Update password
        $this->userService->updatePassword($user, $password);

        // Delete reset token
        \DB::table('password_reset_tokens')
            ->where('email', $email)
            ->delete();

        return [
            'success' => true,
            'message' => 'Password has been reset successfully',
        ];
    }

    /**
     * Get authenticated user with relations.
     */
    public function getAuthenticatedUser(): ?User
    {
        return Auth::user()?->load(['addresses']);
    }
}
