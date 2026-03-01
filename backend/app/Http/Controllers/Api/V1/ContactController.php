<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\BaseController;
use App\Http\Requests\Api\V1\ContactRequest;
use App\Services\ContactService;
use Illuminate\Http\JsonResponse;

class ContactController extends BaseController
{
    public function __construct(
        private readonly ContactService $contactService
    ) {}

    /**
     * Store contact form submission.
     */
    public function store(ContactRequest $request): JsonResponse
    {
        $validated = $request->validated();

        try {
            $message = $this->contactService->storeMessage([
                'name' => $validated['name'],
                'email' => $validated['email'],
                'subject' => $validated['subject'],
                'message' => $validated['message'],
                'phone' => $validated['phone'] ?? null,
                'ip_address' => $request->ip(),
            ]);

            return $this->successResponse([], 'Message sent successfully', 201);
        } catch (\RuntimeException $e) {
            return $this->errorResponse($e->getMessage(), 500);
        }
    }
}
