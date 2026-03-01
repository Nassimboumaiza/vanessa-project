<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\BaseController;
use App\Http\Requests\Api\V1\ForgotPasswordRequest;
use App\Http\Requests\Api\V1\LoginRequest;
use App\Http\Requests\Api\V1\RegisterRequest;
use App\Http\Requests\Api\V1\ResetPasswordRequest;
use App\Http\Resources\Api\V1\UserResource;
use App\Services\AuthService;
use App\Services\UserService;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AuthController extends BaseController
{
    public function __construct(
        private readonly AuthService $authService,
        private readonly UserService $userService
    ) {}

    /**
     * Register a new user.
     */
    public function register(RegisterRequest $request): JsonResponse
    {
        $validated = $request->validated();

        try {
            $user = $this->userService->createUser([
                'first_name' => $validated['first_name'],
                'last_name' => $validated['last_name'],
                'email' => $validated['email'],
                'password' => $validated['password'],
                'phone' => $validated['phone'] ?? null,
            ]);

            event(new Registered($user));

            $token = $user->createToken('auth_token')->plainTextToken;

            return $this->successResponse(
                ['user' => new UserResource($user), 'token' => $token],
                'User registered successfully',
                201
            );
        } catch (\RuntimeException $e) {
            return $this->errorResponse($e->getMessage(), 400);
        }
    }

    /**
     * Login user and create token.
     */
    public function login(LoginRequest $request): JsonResponse
    {
        $validated = $request->validated();

        $result = $this->authService->attemptLogin([
            'email' => $validated['email'],
            'password' => $validated['password'],
            'remember' => $validated['remember'] ?? false,
        ]);

        if (! $result['success']) {
            $statusCode = $result['status'] ?? 401;
            return $this->errorResponse($result['message'], $statusCode);
        }

        return $this->successResponse(
            ['user' => new UserResource($result['user']), 'token' => $result['token']],
            $result['message']
        );
    }

    /**
     * Logout user and revoke token.
     */
    public function logout(Request $request): JsonResponse
    {
        $user = $request->user();

        if ($user) {
            $this->authService->logout($user);
        }

        return $this->successResponse([], 'Logout successful');
    }

    /**
     * Refresh token.
     */
    public function refresh(Request $request): JsonResponse
    {
        $user = $request->user();
        $token = $this->authService->refreshToken($user);

        return $this->successResponse(
            ['user' => new UserResource($user), 'token' => $token],
            'Token refreshed successfully'
        );
    }

    /**
     * Send password reset link.
     */
    public function forgotPassword(ForgotPasswordRequest $request): JsonResponse
    {
        $validated = $request->validated();

        $result = $this->authService->sendPasswordResetLink($validated['email']);

        return $this->successResponse([], $result['message']);
    }

    /**
     * Reset password.
     */
    public function resetPassword(ResetPasswordRequest $request): JsonResponse
    {
        $validated = $request->validated();

        $result = $this->authService->resetPassword($validated);

        if (! $result['success']) {
            return $this->errorResponse($result['message'], 400);
        }

        return $this->successResponse([], $result['message']);
    }
}
