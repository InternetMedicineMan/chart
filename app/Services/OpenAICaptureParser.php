<?php

namespace App\Services;

use App\Models\Capture;
use App\Models\CaptureAttempt;
use App\Models\Domain;
use App\Models\Person;
use App\Models\Project;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Throwable;

class OpenAICaptureParser implements CaptureParser
{
    public function parse(Capture $capture): array
    {
        $attempt = CaptureAttempt::create(['user_id' => $capture->user_id, 'capture_id' => $capture->id, 'model' => config('chart.capture.model'), 'status' => 'started']);
        try {
            $context = [
                'timezone' => $capture->timezone,
                'client_captured_at' => $capture->client_captured_at->setTimezone($capture->timezone)->toIso8601String(),
                'current_local_time' => now()->setTimezone($capture->timezone)->toIso8601String(),
                'source' => $capture->source, 'mode' => $capture->mode,
                'domains' => Domain::forUser($capture->user_id)->whereNull('archived_at')->get(['name', 'sphere']),
                'projects' => Project::forUser($capture->user_id)->where('lifecycle', 'active')->with('domain:id,name')->get(['id', 'name', 'domain_id']),
                'people' => Person::forUser($capture->user_id)->get(['name', 'company']),
            ];
            $response = Http::withToken(config('chart.capture.key'))->acceptJson()->connectTimeout(5)->timeout(45)
                ->post('https://api.openai.com/v1/responses', [
                    'model' => config('chart.capture.model'), 'store' => false, 'max_output_tokens' => 12000,
                    'input' => [
                        ['role' => 'system', 'content' => app(CaptureActions::class)->prompt()],
                        ['role' => 'user', 'content' => json_encode(['context' => $context, 'captured_text' => $capture->raw_text], JSON_THROW_ON_ERROR)],
                    ],
                    'text' => ['format' => ['type' => 'json_schema', 'name' => 'chart_capture', 'strict' => true, 'schema' => app(CaptureActions::class)->schema()]],
                ]);
            $attempt->update(['input_tokens' => $response->json('usage.input_tokens'), 'output_tokens' => $response->json('usage.output_tokens')]);
            if (! $response->successful() || $response->json('status') !== 'completed') {
                throw new RuntimeException('AI response did not complete.');
            }
            $output = '';
            foreach ($response->json('output', []) as $message) {
                foreach ($message['content'] ?? [] as $content) {
                    if (($content['type'] ?? '') === 'refusal') {
                        throw new RuntimeException('AI response declined.');
                    }
                    if (($content['type'] ?? '') === 'output_text') {
                        $output .= $content['text'];
                    }
                }
            }
            $parsed = json_decode($output, true, 512, JSON_THROW_ON_ERROR);
            if (! is_array($parsed) || ! isset($parsed['actions']) || ! is_array($parsed['actions']) || ! array_is_list($parsed['actions']) || count($parsed['actions']) < 1 || count($parsed['actions']) > 50) {
                throw new RuntimeException('AI response has no usable items.');
            }
            $attempt->update(['status' => 'completed']);

            return $parsed;
        } catch (Throwable $exception) {
            $attempt->update(['status' => 'failed']);
            throw new RuntimeException('Automatic sorting could not finish. Your original text is saved.', 0, $exception);
        }
    }
}
