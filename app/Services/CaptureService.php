<?php

namespace App\Services;

use App\Jobs\ParseCapture;
use App\Models\Capture;
use App\Models\CaptureItem;
use App\Models\Task;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Throwable;

class CaptureService
{
    public function receive(User $user, array $data): Capture
    {
        $capture = Capture::forUser($user)->firstOrCreate(['request_key' => $data['request_key']], [
            'user_id' => $user->id, 'raw_text' => $data['text'], 'source' => $data['source'] ?? 'in_app',
            'device_label' => $data['device_label'] ?? null, 'capture_token_id' => $data['capture_token_id'] ?? null,
            'mode' => ($data['mode'] ?? 'single') === 'dump' || count(preg_split('/\s+/u', trim($data['text']))) > 60 ? 'dump' : 'single',
            'client_captured_at' => CarbonImmutable::parse($data['captured_at'])->utc(),
            'timezone' => app(LocalDate::class)->timezone($user), 'available_at' => now(),
        ]);
        if ($capture->raw_text !== $data['text'] || ! $capture->client_captured_at->equalTo(CarbonImmutable::parse($data['captured_at'])->startOfSecond())) {
            throw ValidationException::withMessages(['request_key' => 'This request ID already belongs to different words or a different capture time.']);
        }
        if ($capture->wasRecentlyCreated) {
            if (! $this->enabled()) {
                $this->fallback($capture, 'Automatic sorting is not connected yet. Saved to Inbox; retry after setup.');
            } else {
                $this->dispatch($capture);
            }
        }

        return $capture->fresh();
    }

    public function enabled(): bool
    {
        return (bool) config('chart.capture.enabled') && filled(config('chart.capture.key'));
    }

    public function dispatch(Capture $capture): void
    {
        try {
            $connection = config('chart.capture.connection');
            if (! in_array(config("queue.connections.{$connection}.driver"), ['database', 'redis', 'sqs', 'beanstalkd'], true)) {
                throw new \RuntimeException('An asynchronous queue is required.');
            }
            Bus::dispatch((new ParseCapture($capture->id, $capture->user_id))->onConnection($connection)->onQueue(config('chart.capture.queue')));
            $capture->update(['dispatched_at' => now()]);
        } catch (Throwable $exception) {
            $this->fallback($capture, 'Sorting is waiting for the background worker. Your words are saved in Inbox.');
        }
    }

    public function fallback(Capture $capture, string $message): void
    {
        DB::transaction(function () use ($capture, $message) {
            $capture = Capture::forUser($capture->user_id)->lockForUpdate()->findOrFail($capture->id);
            if ($capture->parsed || in_array($capture->status, ['executed', 'partially_executed'], true)) {
                return;
            }
            if (! $capture->fallback_task_id) {
                $task = Task::create([
                    'user_id' => $capture->user_id, 'domain_id' => app(WorkSetup::class)->inbox(User::findOrFail($capture->user_id))->id,
                    'title' => Str::limit(Str::words(Str::squish($capture->raw_text), 8, ''), 250),
                    'notes' => $capture->raw_text."\n\nCapture: ".route('captures.show', $capture->id), 'needs_review' => true,
                ]);
                $capture->fallback_task_id = $task->id;
                $capture->fallback_snapshot = $task->fresh()->getRawOriginal();
            }
            $capture->fill(['status' => 'failed', 'error' => $message])->save();
        });
    }

    public function retry(Capture $capture): void
    {
        if (! $this->enabled()) {
            throw ValidationException::withMessages(['capture' => 'Connect OpenAI and enable automatic sorting before retrying.']);
        }
        DB::transaction(function () use ($capture) {
            $record = Capture::forUser($capture->user_id)->lockForUpdate()->findOrFail($capture->id);
            if ($record->parsed || ($record->lease_until && $record->lease_until->isFuture())) {
                throw ValidationException::withMessages(['capture' => 'This capture already has items or is being sorted. Review its individual items below.']);
            }
            $record->update(['status' => 'received', 'attempts' => 0, 'available_at' => now(), 'lease' => null, 'lease_until' => null, 'error' => null]);
        });
        $this->dispatch($capture);
    }

    public function summarize(Capture $capture): void
    {
        DB::transaction(function () use ($capture) {
            $capture = Capture::forUser($capture->user_id)->lockForUpdate()->findOrFail($capture->id);
            $statuses = CaptureItem::forUser($capture->user_id)->where('capture_id', $capture->id)->pluck('status');
            if ($statuses->isEmpty()) {
                return;
            }
            $unresolved = $statuses->diff(['executed', 'undone'])->count();
            if ($statuses->contains('pending')) {
                $capture->update(['status' => 'parsed']);

                return;
            }
            $capture->update(['status' => $unresolved ? ($statuses->contains('executed') ? 'partially_executed' : 'needs_triage') : 'executed', 'lease' => null, 'lease_until' => null]);
        });
    }

    public function confirmation(Capture $capture): array
    {
        $statuses = $capture->items()->forUser($capture->user_id)->pluck('status');
        $filed = $statuses->filter(fn ($status) => $status === 'executed')->count();
        $review = $statuses->diff(['executed', 'undone'])->count();
        $message = $capture->fallback_task_id && ! $capture->parsed ? 'Saved to Inbox. Automatic sorting needs attention.' : 'Saved. Sorting it now.';
        if ($statuses->isNotEmpty() && ! $statuses->contains('pending')) {
            $message = "Saved. {$filed} filed".($review ? "; {$review} need review." : '.');
        }

        return ['capture_id' => $capture->id, 'status' => $capture->status, 'item_count' => $statuses->count(), 'spoken_confirmation' => $message];
    }
}
