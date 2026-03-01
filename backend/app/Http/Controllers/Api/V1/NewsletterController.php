<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\BaseController;
use App\Http\Requests\Api\V1\NewsletterSubscribeRequest;
use App\Services\NewsletterService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class NewsletterController extends BaseController
{
    public function __construct(
        private readonly NewsletterService $newsletterService
    ) {}

    /**
     * Subscribe to newsletter.
     */
    public function subscribe(NewsletterSubscribeRequest $request): JsonResponse
    {
        $validated = $request->validated();

        try {
            $subscriber = $this->newsletterService->subscribe([
                'email' => $validated['email'],
                'first_name' => $validated['first_name'] ?? null,
                'last_name' => $validated['last_name'] ?? null,
                'ip_address' => $request->ip(),
            ]);

            return $this->successResponse([], 'Subscribed successfully', 201);
        } catch (\RuntimeException $e) {
            return $this->errorResponse($e->getMessage(), 422);
        }
    }
}
