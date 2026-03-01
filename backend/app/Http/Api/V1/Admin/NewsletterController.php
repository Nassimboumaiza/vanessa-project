<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Api\BaseController;
use App\Http\Resources\Api\V1\NewsletterSubscriberResource;
use App\Models\NewsletterSubscriber;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class NewsletterController extends BaseController
{
    /**
     * Display a listing of subscribers.
     */
    public function index(Request $request): JsonResponse
    {
        $query = NewsletterSubscriber::query();

        if ($request->has('status')) {
            $query->where('status', $request->get('status'));
        }

        if ($request->has('search')) {
            $search = $request->get('search');
            $query->where(function ($q) use ($search): void {
                $q->where('email', 'like', "%{$search}%")
                    ->orWhere('first_name', 'like', "%{$search}%")
                    ->orWhere('last_name', 'like', "%{$search}%");
            });
        }

        $perPage = (int) $request->get('per_page', config('api.pagination.default_per_page'));
        $subscribers = $query->orderBy('created_at', 'desc')->paginate($perPage);

        $stats = [
            'total' => NewsletterSubscriber::count(),
            'subscribed' => NewsletterSubscriber::where('status', 'subscribed')->count(),
            'unsubscribed' => NewsletterSubscriber::where('status', 'unsubscribed')->count(),
            'bounced' => NewsletterSubscriber::where('status', 'bounced')->count(),
        ];

        return $this->successResponse([
            'stats' => $stats,
            'subscribers' => NewsletterSubscriberResource::collection($subscribers),
        ], 'Subscribers retrieved successfully');
    }

    /**
     * Remove the specified subscriber.
     */
    public function destroy(int $id): JsonResponse
    {
        $subscriber = NewsletterSubscriber::findOrFail($id);
        $subscriber->delete();

        return $this->noContentResponse();
    }
}
