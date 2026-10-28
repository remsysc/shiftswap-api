<?php

namespace App\Http\Controllers;

use App\Http\Resources\NotificationResource;
use App\Models\Business;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Notifications\DatabaseNotification;

class NotificationController extends Controller
{
    /**
     * List in-app notifications for the authenticated user.
     */
    public function index(Request $request, Business $business): AnonymousResourceCollection
    {
        /** @var User $user */
        $user = $request->user();

        $notifications = $user->notifications()->latest()->get();

        return NotificationResource::collection($notifications);
    }

    /**
     * Mark a specific notification as read.
     */
    public function read(Request $request, Business $business, string $notification): Response
    {
        /** @var User $user */
        $user = $request->user();

        /** @var DatabaseNotification|null $notif */
        $notif = $user->notifications()->where('id', $notification)->first();

        abort_if($notif === null, 404, 'Notification not found.');

        $notif->markAsRead();

        return response()->noContent();
    }

    /**
     * Mark all unread notifications as read.
     */
    public function readAll(Request $request, Business $business): Response
    {
        /** @var User $user */
        $user = $request->user();

        $user->unreadNotifications->markAsRead();

        return response()->noContent();
    }
}
