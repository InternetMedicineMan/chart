<?php

return [
    'owner_id' => env('CHART_OWNER_ID'),
    'calendar' => [
        'client_id' => env('GOOGLE_CALENDAR_CLIENT_ID'),
        'client_secret' => env('GOOGLE_CALENDAR_CLIENT_SECRET'),
        'redirect_uri' => env('GOOGLE_CALENDAR_REDIRECT_URI'),
        'queue_connection' => env('CHART_CALENDAR_QUEUE_CONNECTION', 'calendar_database'),
        'queue' => 'calendar',
    ],
    'push' => [
        'public_key' => env('CHART_PUSH_PUBLIC_KEY'),
        'private_key' => env('CHART_PUSH_PRIVATE_KEY'),
        'subject' => env('CHART_PUSH_SUBJECT', env('APP_URL')),
        'connection' => env('CHART_PUSH_QUEUE_CONNECTION', 'database'),
        'queue' => 'notifications',
    ],
    'capture' => [
        'enabled' => env('CHART_AI_ENABLED', false),
        'model' => env('CHART_AI_MODEL', 'gpt-6-luna'),
        'key' => env('OPENAI_KEY'),
        'connection' => env('CHART_CAPTURE_QUEUE_CONNECTION', 'database'),
        'queue' => 'captures',
    ],
];
