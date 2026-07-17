<?php

namespace App\Http\Controllers;

use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Notifications\DatabaseNotification;

class NotificationController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $notifications = $request->user()->notifications()->latest()->limit(20)->get();

        return response()->json([
            'notifications' => $notifications,
            'unread_count' => $request->user()->unreadNotifications()->count(),
        ]);
    }

    public function markRead(Request $request, DatabaseNotification $notification): JsonResponse
    {
        $this->authorizeOwnership($request, $notification);

        $notification->markAsRead();

        return response()->json(status: 204);
    }

    public function markAllRead(Request $request): JsonResponse
    {
        $request->user()->unreadNotifications()->update(['read_at' => now()]);

        return response()->json(status: 204);
    }

    private function authorizeOwnership(Request $request, DatabaseNotification $notification): void
    {
        if ($notification->notifiable_id !== $request->user()->id || $notification->notifiable_type !== $request->user()->getMorphClass()) {
            throw new AuthorizationException;
        }
    }
}
