<?php

namespace App\Jobs;

use App\Models\FeedNotification;
use App\Models\PushDelivery;
use App\Models\PushSubscription;
use App\Models\Reminder;
use App\Models\User;
use App\Services\BrowserPush;
use App\Services\ReminderDelivery;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\DB;
use Throwable;

class SendBrowserPush implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    public int $timeout = 45;

    public int $uniqueFor = 60;

    public function __construct(public int $userId, public int $deliveryId) {}

    public function uniqueId(): string
    {
        return $this->userId.':'.$this->deliveryId;
    }

    public function handle(BrowserPush $push): void
    {
        if (! $push->configured()) {
            return;
        }
        $claimed = DB::transaction(function () {
            $user = User::whereKey($this->userId)->lockForUpdate()->firstOrFail();
            $delivery = PushDelivery::forUser($user)->find($this->deliveryId);
            if (! $delivery || $delivery->status !== 'pending' || $delivery->available_at->isFuture()) {
                return false;
            }
            if ($delivery->attempts >= 5 || $delivery->created_at->lt(now()->subDay())) {
                $delivery->update(['status' => 'failed']);

                return false;
            }
            $delivery->update(['attempts' => $delivery->attempts + 1, 'available_at' => now()->addMinutes(2)]);

            return true;
        });
        if (! $claimed) {
            return;
        }
        DB::transaction(function () use ($push) {
            $user = User::whereKey($this->userId)->lockForUpdate()->firstOrFail();
            $delivery = PushDelivery::forUser($user)->find($this->deliveryId);
            if (! $delivery || $delivery->status !== 'pending') {
                return;
            }
            $subscription = PushSubscription::forUser($user)->find($delivery->push_subscription_id);
            $notification = FeedNotification::forUser($user)->find($delivery->notification_id);
            $reminder = Reminder::forUser($user)->where('notification_id', $delivery->notification_id)->first();
            if (! $subscription || ! $notification || $notification->status !== 'unread' || ($reminder && ! isset(app(ReminderDelivery::class)->candidates($user)[$reminder->dedup_key]))) {
                $delivery->update(['status' => 'cancelled']);

                return;
            }
            try {
                $result = $push->send($subscription, $notification->id);
            } catch (Throwable) {
                $result = 'retry';
            }
            if ($result === 'expired') {
                $subscription->delete();
            } else {
                $delivery->update(['status' => $result === 'sent' ? 'sent' : ($delivery->attempts >= 5 ? 'failed' : 'pending'), 'available_at' => now()->addMinutes(2 ** $delivery->attempts)]);
            }
        });
    }
}
