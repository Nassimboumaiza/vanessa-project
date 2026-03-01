<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\BaseController;
use App\Http\Requests\Api\V1\StoreAddressRequest;
use App\Http\Requests\Api\V1\UpdateAddressRequest;
use App\Http\Requests\Api\V1\UpdatePasswordRequest;
use App\Http\Requests\Api\V1\UpdateProfileRequest;
use App\Http\Resources\Api\V1\AddressResource;
use App\Http\Resources\Api\V1\UserResource;
use App\Services\UserService;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class UserController extends BaseController
{
    public function __construct(
        private readonly UserService $userService
    ) {}

    /**
     * Get current authenticated user.
     */
    public function getCurrentUser(Request $request): JsonResponse
    {
        $user = $request->user();

        if (! $user) {
            return $this->errorResponse('Unauthenticated', 401);
        }

        return $this->successResponse(
            new UserResource($user),
            'User retrieved successfully'
        );
    }

    /**
     * Update user profile.
     */
    public function updateProfile(UpdateProfileRequest $request): JsonResponse
    {
        $user = $request->user();
        $validated = $request->validated();

        try {
            $updatedUser = $this->userService->updateProfile($user, $validated);

            return $this->successResponse(
                new UserResource($updatedUser),
                'Profile updated successfully'
            );
        } catch (\RuntimeException $e) {
            return $this->errorResponse($e->getMessage(), 400);
        }
    }

    /**
     * Update password.
     */
    public function updatePassword(UpdatePasswordRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $user = $request->user();

        // Verify current password
        if (! $this->userService->verifyPassword($user, $validated['current_password'])) {
            return $this->errorResponse('Current password is incorrect', 422);
        }

        $this->userService->updatePassword($user, $validated['password']);

        return $this->successResponse([], 'Password updated successfully');
    }

    /**
     * Get user addresses.
     */
    public function addresses(Request $request): JsonResponse
    {
        $user = $request->user();
        $addresses = $this->userService->getUserAddresses($user);

        return $this->successResponse(
            AddressResource::collection($addresses),
            'Addresses retrieved successfully'
        );
    }

    /**
     * Create new address.
     */
    public function createAddress(StoreAddressRequest $request): JsonResponse
    {
        $user = $request->user();
        $validated = $request->validated();

        try {
            $address = $this->userService->createAddress($user, $validated);

            return $this->successResponse(
                new AddressResource($address),
                'Address created successfully',
                201
            );
        } catch (\RuntimeException $e) {
            return $this->errorResponse($e->getMessage(), 400);
        }
    }

    /**
     * Update address.
     */
    public function updateAddress(UpdateAddressRequest $request, int $id): JsonResponse
    {
        $user = $request->user();

        try {
            $addresses = $this->userService->getUserAddresses($user);
            $address = $addresses->firstWhere('id', $id);

            if (! $address) {
                return $this->errorResponse('Address not found', 404);
            }

            $validated = $request->validated();
            $updatedAddress = $this->userService->updateAddress($address, $validated);

            return $this->successResponse(
                new AddressResource($updatedAddress),
                'Address updated successfully'
            );
        } catch (ModelNotFoundException $e) {
            return $this->errorResponse('Address not found', 404);
        }
    }

    /**
     * Delete address.
     */
    public function deleteAddress(Request $request, int $id): JsonResponse
    {
        $user = $request->user();

        try {
            $addresses = $this->userService->getUserAddresses($user);
            $address = $addresses->firstWhere('id', $id);

            if (! $address) {
                return $this->errorResponse('Address not found', 404);
            }

            $this->userService->deleteAddress($address);

            return $this->successResponse([], 'Address deleted successfully');
        } catch (ModelNotFoundException $e) {
            return $this->errorResponse('Address not found', 404);
        }
    }
}
