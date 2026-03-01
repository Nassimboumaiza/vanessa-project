<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Api\BaseController;
use App\Http\Requests\Api\V1\UpdateUserRequest;
use App\Http\Resources\Api\V1\UserCollection;
use App\Http\Resources\Api\V1\UserResource;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class UserController extends BaseController
{
    /**
     * Display a listing of users.
     */
    public function index(Request $request): JsonResponse
    {
        $query = User::withCount(['orders', 'reviews']);

        if ($request->has('role')) {
            $query->where('role', $request->get('role'));
        }

        if ($request->has('search')) {
            $search = $request->get('search');
            $query->where(function ($q) use ($search): void {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%");
            });
        }

        if ($request->has('is_active')) {
            $query->where('is_active', $request->boolean('is_active'));
        }

        $perPage = (int) $request->get('per_page', config('api.pagination.default_per_page', 15));
        $users = $query->orderBy('created_at', 'desc')->paginate($perPage);

        return $this->paginatedResponse(new UserCollection($users), 'Users retrieved successfully');
    }

    /**
     * Display the specified user.
     */
    public function show(int $id): JsonResponse
    {
        $user = User::with(['orders', 'addresses', 'reviews'])->findOrFail($id);

        return $this->successResponse(new UserResource($user), 'User retrieved successfully');
    }

    /**
     * Update the specified user.
     */
    public function update(UpdateUserRequest $request, int $id): JsonResponse
    {
        $user = User::findOrFail($id);
        $validated = $request->validated();

        $updateData = array_filter($validated, fn ($value) => $value !== null);

        if (isset($updateData['password'])) {
            $updateData['password'] = Hash::make($updateData['password']);
        }

        $user->update($updateData);

        return $this->successResponse(new UserResource($user->fresh()), 'User updated successfully');
    }

    /**
     * Remove the specified user.
     */
    public function destroy(int $id): JsonResponse
    {
        $user = User::findOrFail($id);

        if ($user->id === auth()->id()) {
            return $this->errorResponse('Cannot delete yourself', 422);
        }

        // Soft delete
        $user->delete();

        return $this->noContentResponse();
    }
}
