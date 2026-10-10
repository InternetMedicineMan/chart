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
        $push = app(WebPush::class, [
            'auth' => ['VAPID' => ['subject' => config('chart.push.subject'), 'publicKey' => config('chart.push.public_key'), 'privateKey' => config('chart.push.private_key')]],
            'defaultOptions' => ['TTL' => 3600],
            'timeout' => 15,
            'clientOptions' => ['connect_timeout' => 5, 'allow_redirects' => false],
        ]);
        $payload = [
            'id' => $notificationId,
            'web_push' => 8030,
            'notification' => [
                'title' => 'Chart reminder',
                'body' => 'Open Chart to view your notification.',
                'navigate' => rtrim(config('app.url'), '/').'/notifications',
                'tag' => 'chart-'.$notificationId,
                'silent' => false,
            ],
        ];
        $report = $push->sendOneNotification(Subscription::create($subscription->subscription + ['contentEncoding' => 'aes128gcm']), json_encode($payload, JSON_THROW_ON_ERROR));

        return $report->isSuccess() ? 'sent' : ($report->isSubscriptionExpired() ? 'expired' : 'retry');
    }
}
