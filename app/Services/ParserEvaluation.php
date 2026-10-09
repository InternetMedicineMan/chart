<?php

namespace App\Services;

use Illuminate\Validation\ValidationException;
use RuntimeException;

class ParserEvaluation
{
    public function fixtures(): array
    {
        // JSON is a YAML subset, so the fixture file works without a production YAML dependency.
        $fixtures = json_decode(file_get_contents(base_path('tests/parser/fixtures.yaml')), true, 512, JSON_THROW_ON_ERROR);
        if (! isset($fixtures['context'], $fixtures['cases']) || ! is_array($fixtures['cases'])) {
            throw new RuntimeException('Invalid parser fixture file.');
        }

        return $fixtures;
    }

    public function grade(array $case, array $parsed): array
    {
        $items = app(CaptureTranscript::class)->items($case['text'], $parsed['actions']);
        $errors = [];
        foreach ($items as $index => $action) {
            try {
                app(CaptureActions::class)->validate($action);
                if (! str_contains($case['text'], $action['excerpt'])) {
                    $errors[] = 'Item '.($index + 1).' has an untraceable excerpt.';
                }
            } catch (ValidationException $exception) {
                $errors[] = 'Item '.($index + 1).': '.collect($exception->errors())->flatten()->implode(' ');
            }
        }
        if (count($items) !== count($case['expected'])) {
            $errors[] = 'Expected '.count($case['expected']).' items, got '.count($items).'.';
        }
        if (! $this->matchesAll($case['expected'], $items)) {
            $errors[] = 'Expected actions or fields were missing or different: '.json_encode($case['expected'], JSON_UNESCAPED_UNICODE);
        }

        return ['passed' => $errors === [], 'differences' => $errors, 'items' => $items];
    }

    private function matchesAll(array $expectations, array $items): bool
    {
        if ($expectations === []) {
            return $items === [];
        }
        $expected = array_shift($expectations);
        foreach ($items as $index => $item) {
            if ($this->matches($expected, $item)) {
                $remaining = $items;
                unset($remaining[$index]);
                if ($this->matchesAll($expectations, array_values($remaining))) {
                    return true;
                }
            }
        }

        return false;
    }

    private function matches(array $expected, array $item): bool
    {
        foreach ($expected as $field => $value) {
            if (str_ends_with($field, '_contains')) {
                $actual = $item[substr($field, 0, -9)] ?? '';
                if (! is_string($actual) || ! str_contains(mb_strtolower($actual), mb_strtolower($value))) {
                    return false;
                }
            } elseif (($item[$field] ?? null) !== $value) {
                return false;
            }
        }
        if (($item['type'] ?? '') !== 'needs_triage' && ($item['confidence'] ?? 0) < (in_array($item['type'] ?? '', CaptureWorkActions::TYPES, true) ? .8 : .6)) {
            return false;
        }

        return true;
    }
}
