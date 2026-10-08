<?php

namespace App\Services;

use App\Models\Capture;
use App\Models\CaptureItem;
use App\Models\Task;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Throwable;

class CaptureProcessor
{
    public function process(int $id, int $userId): void
    {
        $service = app(CaptureService::class);
        $capture = DB::transaction(function () use ($id, $userId, $service) {
            $capture = Capture::forUser($userId)->lockForUpdate()->find($id);
            if ($capture && $capture->status === 'processing' && $capture->attempts >= 3 && (! $capture->lease_until || $capture->lease_until->isPast())) {
                $capture->update(['lease' => null, 'lease_until' => null]);
                $service->fallback($capture, 'Automatic sorting was interrupted. Saved to Inbox; you can retry.');

                return null;
            }
            if (! $capture || ! in_array($capture->status, ['received', 'processing', 'parsed', 'failed'], true)
                || ($capture->lease_until && $capture->lease_until->isFuture())
                || ($capture->available_at && $capture->available_at->isFuture())
                || (! $capture->parsed && ($capture->attempts >= 3 || ! $service->enabled()))) {
                return null;
            }
            $capture->update(['status' => $capture->parsed ? 'parsed' : 'processing', 'lease' => Str::uuid()->toString(), 'lease_until' => now()->addMinutes(2), 'attempts' => $capture->attempts + ($capture->parsed ? 0 : 1)]);

            return $capture;
        });
        if (! $capture) {
            return;
        }
        if (! $capture->parsed) {
            try {
                $parsed = app(CaptureParser::class)->parse($capture);
                $accepted = DB::transaction(function () use ($capture, $parsed) {
                    $current = Capture::forUser($capture->user_id)->lockForUpdate()->findOrFail($capture->id);
                    if ($current->lease !== $capture->lease) {
                        return false;
                    }
                    if ($current->fallback_task_id) {
                        $fallback = Task::withTrashed()->forUser($current->user_id)->lockForUpdate()->find($current->fallback_task_id);
                        if (! $fallback || $fallback->getRawOriginal() != $current->fallback_snapshot) {
                            $current->update(['status' => 'needs_triage', 'error' => 'The Inbox copy has been changed. Keep working from that copy to avoid duplicate work.', 'lease' => null, 'lease_until' => null]);

                            return false;
                        }
                        $fallback->delete();
                    }
                    foreach ($this->items($capture->raw_text, $parsed['actions']) as $sequence => $action) {
                        $validExcerpt = is_array($action) && is_string($action['excerpt'] ?? null) && $action['excerpt'] !== '' && str_contains($capture->raw_text, $action['excerpt']);
                        $action = is_array($action) ? $action : [];
                        CaptureItem::create([
                            'user_id' => $capture->user_id, 'capture_id' => $capture->id, 'sequence' => $sequence,
                            'excerpt' => $validExcerpt ? $action['excerpt'] : $capture->raw_text,
                            'action_type' => is_string($action['type'] ?? null) && array_key_exists($action['type'], CaptureActions::DEFINITIONS) ? $action['type'] : 'needs_triage',
                            'confidence' => is_numeric($action['confidence'] ?? null) ? max(0, min(1, (float) $action['confidence'])) : 0,
                            'payload' => $action, 'status' => $validExcerpt ? 'pending' : 'needs_triage',
                            'error' => $validExcerpt ? null : 'The proposed item could not be traced to your words. Review it before filing.',
                        ]);
                    }
                    $current->update(['parsed' => $parsed, 'status' => 'parsed', 'error' => null]);

                    return true;
                });
                if (! $accepted) {
                    return;
                }
            } catch (Throwable $exception) {
                DB::transaction(function () use ($capture, $service) {
                    $current = Capture::forUser($capture->user_id)->lockForUpdate()->findOrFail($capture->id);
                    if ($current->lease !== $capture->lease) {
                        return;
                    }
                    $current->update(['lease' => null, 'lease_until' => null, 'available_at' => now()->addSeconds($current->attempts * 60)]);
                    $service->fallback($current, $current->attempts >= 3 ? 'Automatic sorting could not finish after three attempts. Saved to Inbox; you can retry.' : 'Automatic sorting could not finish. Saved to Inbox; another attempt is scheduled.');
                });

                return;
            }
        }
        foreach (CaptureItem::forUser($capture->user_id)->where('capture_id', $capture->id)->where('status', 'pending')->orderBy('sequence')->get() as $item) {
            app(CaptureActions::class)->execute($item);
        }
        $service->summarize($capture);
    }

    /** Keep uncovered words in triage and merge exact duplicate proposals within one dump. */
    private function items(string $text, array $actions): array
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
