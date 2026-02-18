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
use App\Models\UserAddress;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class UserController extends BaseController
{
    /**
     * Update user profile.
     */
    public function updateProfile(UpdateProfileRequest $request): JsonResponse
    {
        $user = $request->user();
        $validated = $request->validated();

        if (isset($validated['first_name']) || isset($validated['last_name'])) {
            $firstName = $validated['first_name'] ?? explode(' ', $user->name)[0] ?? '';
            $lastName = $validated['last_name'] ?? explode(' ', $user->name)[1] ?? '';
            $validated['name'] = trim($firstName.' '.$lastName);
        }

        $user->update($validated);

        return $this->successResponse(new UserResource($user->fresh()), 'Profile updated successfully');
    }

    /**
     * Update password.
     */
    public function updatePassword(UpdatePasswordRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $user = $request->user();

        if (! Hash::check($validated['current_password'], $user->password)) {
            return $this->errorResponse('Current password is incorrect', 422);
        }

        $user->update([
            'password' => Hash::make($validated['password']),
        ]);

        return $this->successResponse([], 'Password updated successfully');
    }

    /**
     * Get user addresses.
     */
    public function addresses(): JsonResponse
    {
        $addresses = UserAddress::where('user_id', auth()->id())
            ->orderBy('is_default', 'desc')
            ->orderBy('created_at', 'desc')
            ->get();

        return $this->successResponse(AddressResource::collection($addresses), 'Addresses retrieved successfully');
    }

    /**
     * Create new address.
     */
    public function createAddress(StoreAddressRequest $request): JsonResponse
    {
        $validated = $request->validated();

        $validated['user_id'] = $request->user()->id;

        if ($validated['is_default'] ?? false) {
            UserAddress::where('user_id', $request->user()->id)
                ->where('type', $validated['type'])
                ->update(['is_default' => false]);
        }

        $address = UserAddress::create($validated);

        return $this->successResponse(new AddressResource($address), 'Address created successfully', 201);
    }

    /**
     * Update address.
     */
    public function updateAddress(UpdateAddressRequest $request, int $id): JsonResponse
    {
        $address = UserAddress::where('user_id', $request->user()->id)
            ->where('id', $id)
            ->first();

        if (! $address) {
            return $this->errorResponse('Address not found', 404);
        }

        $validated = $request->validated();

        if (($validated['is_default'] ?? false) && $validated['is_default']) {
            UserAddress::where('user_id', $request->user()->id)
                ->where('type', $address->type)
                ->where('id', '!=', $id)
                ->update(['is_default' => false]);
        }

        $address->update($validated);

        return $this->successResponse(new AddressResource($address->fresh()), 'Address updated successfully');
    }

    /**
     * Delete address.
     */
    public function deleteAddress(Request $request, int $id): JsonResponse
    {
        $address = UserAddress::where('user_id', $request->user()->id)
            ->where('id', $id)
            ->first();

        if (! $address) {
            return $this->errorResponse('Address not found', 404);
        }

        $address->delete();

        return $this->successResponse([], 'Address deleted successfully');
    }
}
