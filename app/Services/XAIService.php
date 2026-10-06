<?php

namespace App\Services;

use Illuminate\Http\Client\PendingRequest;

class XAIService
{
    private PendingRequest $client;

    public function __construct(PendingRequest $client)
    {
        $this->client = $client;
    }

    public function completion($prompt, $model = 'grok-beta')
    {
        return $this->client->post(config('services.xai.urls.completion'),
            [
                'model' => $model,
                'messages' => [
                    [
                        'role' => 'system',
                        'content' => 'You are an amazing social media content creator',
                    ],
                    [
                        'role' => 'user',
                        'content' => $prompt,
                    ],
                ],
                'stream' => false,
                'temperature' => 0,
            ])->json();
    }
}
