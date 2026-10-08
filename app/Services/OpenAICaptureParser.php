<?php

namespace App\Services;

use App\Exceptions\CaptureParserFailure;
use App\Models\Capture;
use App\Models\CaptureAttempt;
use RuntimeException;
use Throwable;

class OpenAICaptureParser implements CaptureParser
{
    public function parse(Capture $capture): array
    {
        $attempt = CaptureAttempt::create(['user_id' => $capture->user_id, 'capture_id' => $capture->id, 'model' => config('chart.capture.model'), 'status' => 'started']);
        try {
            $result = app(OpenAICaptureClient::class)->generate($capture->raw_text, app(CaptureContext::class)->forCapture($capture));
            $attempt->update(['status' => 'completed', 'model' => $result['model'], 'input_tokens' => $result['usage']['input_tokens'], 'output_tokens' => $result['usage']['output_tokens']]);

            return $result['parsed'];
        } catch (Throwable $exception) {
            $usage = $exception instanceof CaptureParserFailure ? $exception->usage : [];
            $attempt->update(['status' => 'failed', 'input_tokens' => $usage['input_tokens'] ?? null, 'output_tokens' => $usage['output_tokens'] ?? null]);
            throw new RuntimeException('Automatic sorting could not finish. Your original text is saved.', 0, $exception);
        }
    }
}
