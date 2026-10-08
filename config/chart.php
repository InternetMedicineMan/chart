<?php

return [
    'owner_id' => env('CHART_OWNER_ID'),
    'capture' => [
        'enabled' => env('CHART_AI_ENABLED', false),
        'model' => env('CHART_AI_MODEL', 'gpt-6.1-sol'),
        'key' => env('OPENAI_KEY'),
        'connection' => env('CHART_CAPTURE_QUEUE_CONNECTION', 'database'),
        'queue' => 'captures',
    ],
];
