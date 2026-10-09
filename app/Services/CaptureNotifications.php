<?php

namespace App\Services;

use App\Models\ActionLog;
use App\Models\Capture;
use App\Models\FeedNotification;
use Illuminate\Support\Str;

class CaptureNotifications
{
    public function filed(ActionLog $log): void
    {
        $label = match ($log->target_type) {
            'task' => 'Task added',
            'project' => ($log->payload['lifecycle'] ?? 'someday') === 'someday' ? 'Someday project added' : 'Project added',
            default => 'Idea saved',
        };
        if (($log->payload['confidence'] ?? 1) < .8) {
            $label .= ' · check this';
        }

        FeedNotification::forUser($log->user_id)->firstOrCreate(['dedup_key' => 'action:'.$log->id], [
            'user_id' => $log->user_id, 'capture_id' => $log->capture_id, 'action_log_id' => $log->id,
            'type' => 'capture_filed', 'title' => $label,
            'body' => Str::limit($log->payload['title'] ?? $log->payload['body'] ?? '', 500),
            'undo_payload' => ['capture_item_id' => $log->capture_item_id],
        ]);
    }

    /** Called while holding the capture lock, alongside its status change. */
    public function syncCapture(Capture $capture): void
    {
        $query = FeedNotification::forUser($capture->user_id)->where('dedup_key', 'capture:'.$capture->id.':review');
        $notification = $query->first();
        $needsAttention = in_array($capture->status, ['failed', 'needs_triage', 'partially_executed'], true);
        if (! $needsAttention) {
            if ($notification) {
                $sorting = in_array($capture->status, ['received', 'processing', 'parsed'], true);
                $notification->update([
                    'type' => $sorting ? 'capture_sorting' : 'capture_resolved',
                    'title' => $sorting ? 'Capture sorting again' : 'Capture review resolved',
                    'body' => $sorting ? 'Your words are saved. Sorting is in progress.' : 'Every item has been filed or its filing undone. Your original words are still in Intake.',
                    'status' => $notification->status === 'dismissed' ? 'dismissed' : 'read',
                ]);
            }

            return;
        }

        $attributes = [
            'type' => 'capture_review',
            'title' => $capture->status === 'failed' ? 'Capture saved in Inbox' : 'Capture needs review',
            'body' => $capture->error ?: 'Some of your captured words need a decision. Open the capture to review them.',
        ];
        if ($notification) {
            $notification->update($attributes + ['status' => $notification->type !== 'capture_review' && $notification->status !== 'dismissed' ? 'unread' : $notification->status]);
        } else {
            FeedNotification::create($attributes + [
                'user_id' => $capture->user_id, 'capture_id' => $capture->id,
                'dedup_key' => 'capture:'.$capture->id.':review',
            ]);
        }
    }

    public function undone(ActionLog $log): void
    {
        FeedNotification::forUser($log->user_id)->where('action_log_id', $log->id)
            ->update(['undone_at' => $log->undone_at]);
        FeedNotification::forUser($log->user_id)->where('action_log_id', $log->id)->where('status', 'unread')
            ->update(['status' => 'read']);
    }
}
