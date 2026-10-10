<?php

use App\Models\PushSubscription;
use App\Services\BrowserPush;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use Minishlink\WebPush\MessageSentReport;
use Minishlink\WebPush\SubscriptionInterface;
use Minishlink\WebPush\WebPush;

it('sends a private declarative fallback while retaining the legacy worker notification id', function () {
    config(['app.url' => 'https://chart.example/']);
    $subscription = PushSubscription::factory()->make(['user_id' => 1]);
    $endpoint = $subscription->subscription['endpoint'];
    $report = new MessageSentReport(new Request('POST', $endpoint), new Response(201));
    $client = Mockery::mock(WebPush::class);
    $client->shouldReceive('sendOneNotification')->once()->withArgs(function (SubscriptionInterface $target, string $payload) use ($endpoint) {
        $message = json_decode($payload, true, flags: JSON_THROW_ON_ERROR);
        expect($target->getEndpoint())->toBe($endpoint)
            ->and($message['id'])->toBe(42)
            ->and($message['web_push'])->toBe(8030)
            ->and($message['notification']['title'])->toBe('Chart reminder')
            ->and($message['notification']['body'])->toBe('Open Chart to view your notification.')
            ->and($message['notification']['navigate'])->toBe('https://chart.example/notifications')
            ->and($message['notification']['tag'])->toBe('chart-42')
            ->and($message['notification']['silent'])->toBeFalse()
            ->and(array_keys($message))->toBe(['id', 'web_push', 'notification'])
            ->and(array_keys($message['notification']))->toBe(['title', 'body', 'navigate', 'tag', 'silent']);

        return true;
    })->andReturn($report);
    app()->bind(WebPush::class, fn () => $client);

    expect(app(BrowserPush::class)->send($subscription, 42))->toBe('sent');
});

it('preserves expired subscription and transient failure handling', function (int $status, string $result) {
    $subscription = PushSubscription::factory()->make(['user_id' => 1]);
    $report = new MessageSentReport(new Request('POST', $subscription->subscription['endpoint']), new Response($status), false);
    $client = Mockery::mock(WebPush::class);
    $client->shouldReceive('sendOneNotification')->once()->andReturn($report);
    app()->bind(WebPush::class, fn () => $client);

    expect(app(BrowserPush::class)->send($subscription, 42))->toBe($result);
})->with([[404, 'expired'], [410, 'expired'], [503, 'retry']]);
