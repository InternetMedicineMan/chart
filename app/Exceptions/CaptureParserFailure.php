<?php

namespace App\Exceptions;

use RuntimeException;

class CaptureParserFailure extends RuntimeException
{
    public function __construct(public string $reason, public array $usage = [])
    {
        parent::__construct(match ($reason) {
            'missing_key' => 'Add OPENAI_KEY privately to the server environment before testing the parser.',
            'authentication' => 'OpenAI rejected the API key. Check the key configured for this environment.',
            'permission' => 'The API key does not have permission to use this model or endpoint.',
            'model' => 'The configured model is unavailable to this API project. Check CHART_AI_MODEL and model access.',
            'billing' => 'The OpenAI API project has no available quota. Check API billing and credit balance.',
            'rate_limit' => 'OpenAI is rate limiting requests. Wait before trying again.',
            'connection' => 'OpenAI could not be reached within the time limit. Try again later.',
            'incomplete' => 'OpenAI returned an incomplete response. No proposed items were accepted.',
            'refusal' => 'OpenAI declined to parse this text. No proposed items were accepted.',
            'invalid_output' => 'OpenAI returned an unusable result. No proposed items were accepted.',
            default => 'OpenAI could not complete this request. Try again later.',
        });
    }
}
