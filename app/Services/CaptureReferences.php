<?php

namespace App\Services;

use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class CaptureReferences
{
    public function candidates(?string $reference, Collection $records): array
    {
        if (! $reference) {
            return [];
        }
        $query = Str::lower(Str::squish($reference));

        return $records->map(function ($record) use ($query) {
            $name = Str::lower(Str::squish($record->name));
            $score = 0;
            if ($query === $name) {
                $score = 1;
            } elseif (str_contains($name, $query) || str_contains($query, $name)) {
                $score = .6 + .3 * min(mb_strlen($query), mb_strlen($name)) / max(mb_strlen($query), mb_strlen($name));
            } else {
                $words = collect(preg_split('/[^\p{L}\p{N}]+/u', $query))->filter(fn ($word) => mb_strlen($word) >= 3);
                $score = $words->isEmpty() ? 0 : $words->filter(fn ($word) => str_contains($name, $word))->count() / $words->count() * .55;
            }

            return ['id' => $record->id, 'name' => $record->name, 'score' => round($score, 4)];
        })->filter(fn ($candidate) => $candidate['score'] >= .5)->sortByDesc('score')->take(5)->values()->all();
    }

    public function resolve(?string $reference, Collection $records, string $kind): ?int
    {
        if (! $reference) {
            return null;
        }
        $candidates = $this->candidates($reference, $records);
        if (! $candidates || (isset($candidates[1]) && $candidates[0]['score'] - $candidates[1]['score'] <= .10001)) {
            throw ValidationException::withMessages([$kind => "Choose a {$kind} for “{$reference}”."]);
        }

        return $candidates[0]['id'];
    }
}
