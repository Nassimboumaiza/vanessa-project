<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\BaseController;
use App\Http\Requests\Api\V1\ContactRequest;
use Illuminate\Http\JsonResponse;

class ContactController extends BaseController
{
    /**
     * Store contact form submission.
     */
    public function store(ContactRequest $request): JsonResponse
    {
        $validated = $request->validated();

        // TODO: Send email notification to admin
        // Mail::to(config('mail.admin_address'))->send(new ContactFormMail($validated));

        return $this->successResponse([], 'Message sent successfully', 201);
    }
}
