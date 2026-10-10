<?php

namespace App\Http\Controllers;

use App\Http\Requests\NotificationFeedRequest;
use App\Http\Requests\UpdateNotificationRequest;
use App\Models\FeedNotification;
use App\Services\LocalDate;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class NotificationFeedController extends Controller
{
    public function index(NotificationFeedRequest $request, LocalDate $dates): Response
    {
        $status = $request->validated('status') ?: 'all';
        $notifications = FeedNotification::forUser($request->user())
            ->when($status === 'all', fn ($query) => $query->where('status', '!=', 'dismissed'), fn ($query) => $query->where('status', $status))
            ->with(['actionLog' => fn ($query) => $query->forUser($request->user())->select('id', 'user_id', 'status', 'executed_at', 'capture_item_id')])
            ->latest('id')->paginate(20)->withQueryString()
            ->through(function (FeedNotification $notification) {
                $log = $notification->actionLog;

                return [
                    'id' => $notification->id, 'type' => $notification->type, 'title' => $notification->title,
                    'target_url' => $notification->target_url, 'body' => $notification->body, 'status' => $notification->status,
                    'created_at' => $notification->created_at, 'undone_at' => $notification->undone_at,
                    'capture_url' => $notification->capture_id ? route('captures.show', $notification->capture_id) : null,
                    'undo_url' => $log && $log->status === 'ok' && $log->executed_at->gte(now()->subDays(7))
                        && ! $notification->undone_at && $log->capture_item_id
                        ? route('capture-items.undo', $log->capture_item_id) : null,
                ];
            });

        return Inertia::render('Work/Notifications', [
            'notifications' => $notifications, 'filter' => $status, 'timezone' => $dates->timezone($request->user()),
        ]);
    }

    public function update(UpdateNotificationRequest $request, int $notification): RedirectResponse
    {
        FeedNotification::forUser($request->user())->findOrFail($notification)->update($request->validated());

        return back();
    }

    public function readAll(Request $request): RedirectResponse
    {
        FeedNotification::forUser($request->user())->where('status', 'unread')->update(['status' => 'read']);

        return back()->with('message', 'Notifications marked as read.');
    }
}
