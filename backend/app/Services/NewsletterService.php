<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\NewsletterSubscriber;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class NewsletterService
{
    /**
     * Subscribe email to newsletter.
     *
     * @param array<string, mixed> $data
     */
    public function subscribe(array $data): NewsletterSubscriber
    {
        return DB::transaction(function () use ($data) {
            $email = strtolower(trim($data['email']));

            // Check if already subscribed
            $existing = NewsletterSubscriber::query()
                ->where('email', $email)
                ->first();

            if ($existing) {
                if ($existing->is_active) {
                    throw new \RuntimeException('This email is already subscribed to our newsletter');
                }

                // Reactivate subscription
                $existing->update([
                    'is_active' => true,
                    'unsubscribed_at' => null,
                ]);

                return $existing->fresh();
            }

            // Create new subscription
            $subscriber = NewsletterSubscriber::create([
                'email' => $email,
                'first_name' => $data['first_name'] ?? null,
                'last_name' => $data['last_name'] ?? null,
                'is_active' => true,
                'ip_address' => $data['ip_address'] ?? null,
                'subscribed_at' => now(),
            ]);

            return $subscriber;
        });
    }

    /**
     * Unsubscribe email from newsletter.
     */
    public function unsubscribe(string $email): void
    {
        $subscriber = NewsletterSubscriber::query()
            ->where('email', strtolower(trim($email)))
            ->first();

        if (! $subscriber) {
            throw new \RuntimeException('Email not found in our subscriber list');
        }

        if (! $subscriber->is_active) {
            throw new \RuntimeException('This email is already unsubscribed');
        }

        $subscriber->update([
            'is_active' => false,
            'unsubscribed_at' => now(),
        ]);
    }

    /**
     * Unsubscribe by token.
     */
    public function unsubscribeByToken(string $token): void
    {
        $subscriber = NewsletterSubscriber::query()
            ->where('unsubscribe_token', $token)
            ->first();

        if (! $subscriber) {
            throw new \RuntimeException('Invalid unsubscribe token');
        }

        $subscriber->update([
            'is_active' => false,
            'unsubscribed_at' => now(),
        ]);
    }

    /**
     * Get paginated subscribers for admin.
     *
     * @param array<string, mixed> $filters
     */
    public function getPaginatedSubscribers(array $filters = [], int $perPage = 15): LengthAwarePaginator
    {
        $query = NewsletterSubscriber::query();

        if (isset($filters['is_active'])) {
            $query->where('is_active', $filters['is_active']);
        }

        if (! empty($filters['search'])) {
            $query->where(function ($q) use ($filters): void {
                $q->where('email', 'like', "%{$filters['search']}%")
                    ->orWhere('first_name', 'like', "%{$filters['search']}%")
                    ->orWhere('last_name', 'like', "%{$filters['search']}%");
            });
        }

        $sortBy = $filters['sort_by'] ?? 'subscribed_at';
        $sortOrder = $filters['sort_order'] ?? 'desc';

        return $query->orderBy($sortBy, $sortOrder)->paginate($perPage);
    }

    /**
     * Get active subscribers count.
     */
    public function getActiveSubscribersCount(): int
    {
        return NewsletterSubscriber::query()
            ->where('is_active', true)
            ->count();
    }

    /**
     * Get subscriber statistics.
     *
     * @return array<string, mixed>
     */
    public function getSubscriberStatistics(): array
    {
        return [
            'total_subscribers' => NewsletterSubscriber::query()->count(),
            'active_subscribers' => NewsletterSubscriber::query()->where('is_active', true)->count(),
            'unsubscribed_count' => NewsletterSubscriber::query()->where('is_active', false)->count(),
            'new_this_month' => NewsletterSubscriber::query()
                ->where('is_active', true)
                ->whereMonth('subscribed_at', now()->month)
                ->whereYear('subscribed_at', now()->year)
                ->count(),
            'new_today' => NewsletterSubscriber::query()
                ->where('is_active', true)
                ->whereDate('subscribed_at', today())
                ->count(),
        ];
    }

    /**
     * Delete subscriber.
     */
    public function deleteSubscriber(NewsletterSubscriber $subscriber): bool
    {
        return $subscriber->delete();
    }

    /**
     * Find subscriber by ID.
     *
     * @throws ModelNotFoundException
     */
    public function findById(int $id): NewsletterSubscriber
    {
        return NewsletterSubscriber::query()->findOrFail($id);
    }

    /**
     * Find subscriber by email.
     */
    public function findByEmail(string $email): ?NewsletterSubscriber
    {
        return NewsletterSubscriber::query()
            ->where('email', strtolower(trim($email)))
            ->first();
    }

    /**
     * Generate unsubscribe token for subscriber.
     */
    public function generateUnsubscribeToken(NewsletterSubscriber $subscriber): string
    {
        $token = \Illuminate\Support\Str::random(32);

        $subscriber->update(['unsubscribe_token' => $token]);

        return $token;
    }

    /**
     * Export subscribers to CSV.
     *
     * @return array<int, array<string, mixed>>
     */
    public function exportSubscribers(bool $activeOnly = true): array
    {
        $query = NewsletterSubscriber::query();

        if ($activeOnly) {
            $query->where('is_active', true);
        }

        return $query->get()->map(function ($subscriber): array {
            return [
                'email' => $subscriber->email,
                'first_name' => $subscriber->first_name,
                'last_name' => $subscriber->last_name,
                'subscribed_at' => $subscriber->subscribed_at?->toDateTimeString(),
            ];
        })->toArray();
    }

    /**
     * Get active subscribers for newsletter sending.
     */
    public function getActiveSubscribersForSending(?int $batchSize = null): Collection
    {
        $query = NewsletterSubscriber::query()
            ->where('is_active', true);

        if ($batchSize) {
            $query->limit($batchSize);
        }

        return $query->get();
    }
}
