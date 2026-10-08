<?php

namespace App\Services;

class CaptureTranscript
{
    /** Keep uncovered words in triage and merge exact duplicate proposals within one dump. */
    public function items(string $text, array $actions): array
    {
        $items = [];
        $seen = [];
        $ranges = [];
        $untraceable = false;
        foreach ($actions as $action) {
            if (! is_array($action)) {
                $action = [];
            }
            $excerpt = $action['excerpt'] ?? null;
            if (! is_string($excerpt) || $excerpt === '' || ! str_contains($text, $excerpt)) {
                $untraceable = true;
            } else {
                $offset = 0;
                while (($start = strpos($text, $excerpt, $offset)) !== false) {
                    $ranges[] = [$start, $start + strlen($excerpt)];
                    $offset = $start + strlen($excerpt);
                }
            }
            $identity = $action;
            unset($identity['excerpt']);
            ksort($identity);
            $key = json_encode($identity, JSON_THROW_ON_ERROR);
            if (! isset($seen[$key]) || ($action['type'] ?? '') === 'needs_triage') {
                $seen[$key] = true;
                $items[] = $action;
            }
        }
        if ($untraceable) {
            return $items;
        }
        usort($ranges, fn ($a, $b) => $a[0] <=> $b[0]);
        $ranges[] = [strlen($text), strlen($text)];
        $end = 0;
        foreach ($ranges as [$start, $stop]) {
            if ($start > $end) {
                $gap = substr($text, $end, $start - $end);
                $substance = preg_replace('/\b(?:um|uh|also|oh|and|another thing|then)\b/iu', '', $gap);
                if (preg_match('/[\p{L}\p{N}]/u', $substance)) {
                    $items[] = ['type' => 'needs_triage', 'confidence' => 0, 'excerpt' => $gap, 'reason' => 'These words were not included in the proposed items. Review them so nothing is missed.'];
                }
            }
            $end = max($end, $stop);
        }

        return $items;
    }
}
