<?php

namespace App\Services;

use App\Models\PushSubscription;
use Minishlink\WebPush\Subscription;
use Minishlink\WebPush\WebPush;

class BrowserPush
{
    public function configured(): bool
    {
        return filled(config('chart.push.public_key')) && filled(config('chart.push.private_key')) && filled(config('chart.push.subject'));
    }

    public function send(PushSubscription $subscription, int $notificationId): string
    {
        $push = new WebPush(['VAPID' => ['subject' => config('chart.push.subject'), 'publicKey' => config('chart.push.public_key'), 'privateKey' => config('chart.push.private_key')]], ['TTL' => 3600], 15, ['connect_timeout' => 5, 'allow_redirects' => false]);
        $report = $push->sendOneNotification(Subscription::create($subscription->subscription + ['contentEncoding' => 'aes128gcm']), json_encode(['id' => $notificationId], JSON_THROW_ON_ERROR));

        return $report->isSuccess() ? 'sent' : ($report->isSubscriptionExpired() ? 'expired' : 'retry');
    }
}
