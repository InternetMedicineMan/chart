<?php

namespace App\Services;

use App\Exceptions\CaptureParserFailure;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use JsonException;

class OpenAICaptureClient
{
    /** This call parses only: it never stores or executes a capture. */
    public function generate(string $text, array $context, ?string $model = null): array
    {
        if (blank(config('chart.capture.key'))) {
            throw new CaptureParserFailure('missing_key');
        }
        $model ??= config('chart.capture.model');
        $start = hrtime(true);
        try {
            $response = Http::withToken(config('chart.capture.key'))->acceptJson()->connectTimeout(5)->timeout(45)
                ->post('https://api.openai.com/v1/responses', [
                    'model' => $model, 'store' => false, 'max_output_tokens' => 12000,
                    'input' => [
                        ['role' => 'system', 'content' => app(CaptureActions::class)->prompt()],
                        ['role' => 'user', 'content' => json_encode(['context' => $context, 'captured_text' => $text], JSON_THROW_ON_ERROR)],
                    ],
                    'text' => ['format' => ['type' => 'json_schema', 'name' => 'chart_capture', 'strict' => true, 'schema' => app(CaptureActions::class)->schema()]],
                ]);
        } catch (ConnectionException $exception) {
            throw new CaptureParserFailure('connection');
        }
        $usage = [
            'input_tokens' => $response->json('usage.input_tokens'),
            'output_tokens' => $response->json('usage.output_tokens'),
            'cached_input_tokens' => $response->json('usage.input_tokens_details.cached_tokens'),
        ];
        if (! $response->successful()) {
            $message = strtolower((string) $response->json('error.message', ''));
            $billingFailure = in_array($response->json('error.code'), ['insufficient_quota', 'billing_hard_limit_reached', 'billing_not_active', 'insufficient_credits'], true)
                || (str_contains($message, 'billing') && (str_contains($message, 'credit') || str_contains($message, 'quota')));
            $reason = match ($response->status()) {
                401 => 'authentication', 403 => 'permission', 404 => 'model',
                429 => $billingFailure ? 'billing' : 'rate_limit',
                400 => in_array($response->json('error.code'), ['model_not_found', 'unsupported_model'], true) ? 'model' : 'provider',
                default => 'provider',
            };
            throw new CaptureParserFailure($reason, $usage);
        }
        if ($response->json('status') !== 'completed') {
            throw new CaptureParserFailure('incomplete', $usage);
        }
        $output = '';
        $messages = $response->json('output');
        if (! is_array($messages)) {
            throw new CaptureParserFailure('invalid_output', $usage);
        }
        foreach ($messages as $message) {
            if (! is_array($message) || (isset($message['content']) && ! is_array($message['content']))) {
                throw new CaptureParserFailure('invalid_output', $usage);
            }
            foreach ($message['content'] ?? [] as $content) {
                if (! is_array($content)) {
                    throw new CaptureParserFailure('invalid_output', $usage);
                }
                if (($content['type'] ?? '') === 'refusal') {
                    throw new CaptureParserFailure('refusal', $usage);
                }
                if (($content['type'] ?? '') === 'output_text') {
                    if (! is_string($content['text'] ?? null)) {
                        throw new CaptureParserFailure('invalid_output', $usage);
                    }
                    $output .= $content['text'];
                }
            }
        }
        try {
            $parsed = json_decode($output, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            throw new CaptureParserFailure('invalid_output', $usage);
        }
        if (! is_array($parsed) || ! isset($parsed['actions']) || ! is_array($parsed['actions']) || ! array_is_list($parsed['actions']) || count($parsed['actions']) < 1 || count($parsed['actions']) > 50) {
            throw new CaptureParserFailure('invalid_output', $usage);
        }

        return ['parsed' => $parsed, 'usage' => $usage, 'model' => $response->json('model', $model), 'duration_ms' => (int) round((hrtime(true) - $start) / 1_000_000)];
    }
}
