<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\ContactMessage;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;

class ContactService
{
    /**
     * Store contact message.
     *
     * @param array<string, mixed> $data
     */
    public function storeMessage(array $data): ContactMessage
    {
        return DB::transaction(function () use ($data) {
            $message = ContactMessage::create([
                'name' => $data['name'],
                'email' => strtolower(trim($data['email'])),
                'subject' => $data['subject'],
                'message' => $data['message'],
                'phone' => $data['phone'] ?? null,
                'ip_address' => $data['ip_address'] ?? null,
                'is_read' => false,
            ]);

            // Send notification email to admin (optional)
            $this->sendAdminNotification($message);

            return $message;
        });
    }

    /**
     * Send admin notification email.
     */
    private function sendAdminNotification(ContactMessage $message): void
    {
        try {
            $adminEmail = config('mail.admin_address');
            if ($adminEmail) {
                Mail::raw(
                    "New contact message from {$message->name} ({$message->email}):\n\n" .
                    "Subject: {$message->subject}\n\n" .
                    "Message:\n{$message->message}",
                    function ($mail) use ($message, $adminEmail): void {
                        $mail->to($adminEmail)
                            ->subject('New Contact Form Submission: ' . $message->subject);
                    }
                );
            }
        } catch (\Exception $e) {
            // Log error but don't fail the contact submission
            \Illuminate\Support\Facades\Log::error('Failed to send contact notification email', [
                'message_id' => $message->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Mark message as read.
     */
    public function markAsRead(ContactMessage $message): ContactMessage
    {
        $message->update([
            'is_read' => true,
            'read_at' => now(),
        ]);

        return $message->fresh();
    }

    /**
     * Mark message as replied.
     */
    public function markAsReplied(ContactMessage $message): ContactMessage
    {
        $message->update([
            'is_replied' => true,
            'replied_at' => now(),
        ]);

        return $message->fresh();
    }

    /**
     * Delete contact message.
     */
    public function deleteMessage(ContactMessage $message): bool
    {
        return $message->delete();
    }

    /**
     * Get paginated messages for admin.
     *
     * @param array<string, mixed> $filters
     */
    public function getPaginatedMessages(array $filters = [], int $perPage = 15): LengthAwarePaginator
    {
        $query = ContactMessage::query();

        if (isset($filters['is_read'])) {
            $query->where('is_read', $filters['is_read']);
        }

        if (isset($filters['is_replied'])) {
            $query->where('is_replied', $filters['is_replied']);
        }

        if (! empty($filters['search'])) {
            $query->where(function ($q) use ($filters): void {
                $q->where('name', 'like', "%{$filters['search']}%")
                    ->orWhere('email', 'like', "%{$filters['search']}%")
                    ->orWhere('subject', 'like', "%{$filters['search']}%");
            });
        }

        $sortBy = $filters['sort_by'] ?? 'created_at';
        $sortOrder = $filters['sort_order'] ?? 'desc';

        return $query->orderBy($sortBy, $sortOrder)->paginate($perPage);
    }

    /**
     * Get unread messages count.
     */
    public function getUnreadCount(): int
    {
        return ContactMessage::query()
            ->where('is_read', false)
            ->count();
    }

    /**
     * Get recent unread messages.
     */
    public function getRecentUnreadMessages(int $limit = 5): Collection
    {
        return ContactMessage::query()
            ->where('is_read', false)
            ->orderBy('created_at', 'desc')
            ->limit($limit)
            ->get();
    }

    /**
     * Get message by ID.
     *
     * @throws ModelNotFoundException
     */
    public function findById(int $id): ContactMessage
    {
        return ContactMessage::query()->findOrFail($id);
    }

    /**
     * Get contact statistics.
     *
     * @return array<string, mixed>
     */
    public function getContactStatistics(): array
    {
        return [
            'total_messages' => ContactMessage::query()->count(),
            'unread_messages' => ContactMessage::query()->where('is_read', false)->count(),
            'replied_messages' => ContactMessage::query()->where('is_replied', true)->count(),
            'messages_today' => ContactMessage::query()->whereDate('created_at', today())->count(),
            'messages_this_week' => ContactMessage::query()
                ->whereBetween('created_at', [now()->startOfWeek(), now()->endOfWeek()])
                ->count(),
        ];
    }

    /**
     * Reply to contact message.
     *
     * @param array<string, mixed> $replyData
     */
    public function replyToMessage(ContactMessage $message, array $replyData): void
    {
        $replyContent = $replyData['reply_message'];

        Mail::raw($replyContent, function ($mail) use ($message, $replyData): void {
            $mail->to($message->email)
                ->subject('Re: ' . $message->subject);

            if (! empty($replyData['from_email'])) {
                $mail->from($replyData['from_email']);
            }
        });

        $this->markAsReplied($message);
    }
}
