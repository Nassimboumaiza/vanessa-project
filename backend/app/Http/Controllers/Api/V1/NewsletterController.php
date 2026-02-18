<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\BaseController;
use App\Http\Requests\Api\V1\NewsletterSubscribeRequest;
use App\Models\NewsletterSubscriber;
use Illuminate\Http\JsonResponse;

class NewsletterController extends BaseController
{
    /**
     * Subscribe to newsletter.
     */
    public function subscribe(NewsletterSubscribeRequest $request): JsonResponse
    {
        $validated = $request->validated();

        $existing = NewsletterSubscriber::where('email', $validated['email'])->first();

        if ($existing) {
            if ($existing->status === 'subscribed') {
                return $this->errorResponse('Email is already subscribed', 422);
            }

            // Resubscribe
            $existing->update([
                'status' => 'subscribed',
                'subscribed_at' => now(),
                'unsubscribed_at' => null,
            ]);

            return $this->successResponse([], 'Resubscribed successfully');
        }

        NewsletterSubscriber::create([
            'email' => $validated['email'],
            'first_name' => $validated['first_name'] ?? null,
            'last_name' => $validated['last_name'] ?? null,
            'status' => 'subscribed',
            'subscribed_at' => now(),
            'ip_address' => $request->ip(),
        ]);

        return $this->successResponse([], 'Subscribed successfully', 201);
    }
}
