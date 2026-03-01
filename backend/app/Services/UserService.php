<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\User;
use App\Models\Address;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class UserService
{
    /**
     * Create a new user.
     *
     * @param array<string, mixed> $data
     */
    public function createUser(array $data): User
    {
        return DB::transaction(function () use ($data) {
            $user = User::create([
                'name' => trim(($data['first_name'] ?? '') . ' ' . ($data['last_name'] ?? '')),
                'email' => $data['email'],
                'password' => $data['password'],
                'phone' => $data['phone'] ?? null,
                'is_active' => true,
                'email_verified_at' => null,
            ]);

            // Create default address if provided
            if (! empty($data['address'])) {
                $this->createAddress($user, $data['address']);
            }

            return $user->fresh(['addresses']);
        });
    }

    /**
     * Update user profile.
     *
     * @param array<string, mixed> $data
     */
    public function updateProfile(User $user, array $data): User
    {
        $updateData = [];

        if (isset($data['first_name']) || isset($data['last_name'])) {
            $firstName = $data['first_name'] ?? $user->first_name;
            $lastName = $data['last_name'] ?? $user->last_name;
            $updateData['name'] = trim($firstName . ' ' . $lastName);
        }

        if (isset($data['phone'])) {
            $updateData['phone'] = $data['phone'];
        }

        if (! empty($updateData)) {
            $user->update($updateData);
        }

        return $user->fresh();
    }

    /**
     * Update user password.
     */
    public function updatePassword(User $user, string $newPassword): User
    {
        $user->update([
            'password' => $newPassword,
        ]);

        return $user->fresh();
    }

    /**
     * Find user by email.
     */
    public function findByEmail(string $email): ?User
    {
        return User::query()
            ->where('email', $email)
            ->first();
    }

    /**
     * Find user by ID.
     *
     * @throws ModelNotFoundException
     */
    public function findById(int $id): User
    {
        return User::query()->findOrFail($id);
    }

    /**
     * Verify user password.
     */
    public function verifyPassword(User $user, string $password): bool
    {
        return Hash::check($password, $user->password);
    }

    /**
     * Create address for user.
     *
     * @param array<string, mixed> $data
     */
    public function createAddress(User $user, array $data): Address
    {
        // If this is marked as default, unset other defaults
        if (! empty($data['is_default'])) {
            $user->addresses()->update(['is_default' => false]);
        }

        return $user->addresses()->create($data);
    }

    /**
     * Update user address.
     *
     * @param array<string, mixed> $data
     */
    public function updateAddress(Address $address, array $data): Address
    {
        // If setting as default, unset others
        if (! empty($data['is_default']) && ! $address->is_default) {
            $address->user->addresses()->update(['is_default' => false]);
        }

        $address->update($data);

        return $address->fresh();
    }

    /**
     * Delete user address.
     */
    public function deleteAddress(Address $address): bool
    {
        return $address->delete();
    }

    /**
     * Get user addresses.
     */
    public function getUserAddresses(User $user): Collection
    {
        return $user->addresses()->orderBy('is_default', 'desc')->get();
    }

    /**
     * Get paginated users for admin.
     *
     * @param array<string, mixed> $filters
     */
    public function getPaginatedUsers(array $filters = [], int $perPage = 15): LengthAwarePaginator
    {
        $query = User::query();

        if (! empty($filters['search'])) {
            $query->where(function ($q) use ($filters): void {
                $q->where('email', 'like', "%{$filters['search']}%")
                    ->orWhere('first_name', 'like', "%{$filters['search']}%")
                    ->orWhere('last_name', 'like', "%{$filters['search']}%");
            });
        }

        if (isset($filters['is_active'])) {
            $query->where('is_active', $filters['is_active']);
        }

        $sortBy = $filters['sort_by'] ?? 'created_at';
        $sortOrder = $filters['sort_order'] ?? 'desc';

        return $query->orderBy($sortBy, $sortOrder)->paginate($perPage);
    }

    /**
     * Toggle user active status.
     */
    public function toggleActive(User $user): User
    {
        $user->update(['is_active' => ! $user->is_active]);

        return $user->fresh();
    }

    /**
     * Mark email as verified.
     */
    public function markEmailAsVerified(User $user): User
    {
        $user->update([
            'email_verified_at' => now(),
        ]);

        return $user->fresh();
    }

    /**
     * Generate password reset token.
     */
    public function generatePasswordResetToken(User $user): string
    {
        $token = \Illuminate\Support\Str::random(64);

        DB::table('password_reset_tokens')->updateOrInsert(
            ['email' => $user->email],
            [
                'token' => Hash::make($token),
                'created_at' => now(),
            ]
        );

        return $token;
    }

    /**
     * Get user statistics.
     *
     * @return array<string, mixed>
     */
    public function getUserStatistics(): array
    {
        return [
            'total_users' => User::query()->count(),
            'active_users' => User::query()->where('is_active', true)->count(),
            'verified_users' => User::query()->whereNotNull('email_verified_at')->count(),
            'new_users_today' => User::query()->whereDate('created_at', today())->count(),
            'new_users_this_month' => User::query()
                ->whereMonth('created_at', now()->month)
                ->whereYear('created_at', now()->year)
                ->count(),
        ];
    }
}
