<?php

namespace App\Services;

use App\Models\ActivityLog;
use App\Models\Domain;
use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class ActivityTracking
{
    public function save(User $user, array $data, ?int $id = null): void
    {
        DB::transaction(function () use ($user, $data, $id) {
            User::whereKey($user->id)->lockForUpdate()->firstOrFail();
            $timezone = app(LocalDate::class)->timezone($user);
            if ($timezone !== $data['timezone']) {
                throw ValidationException::withMessages(['timezone' => 'Your timezone changed. Reload before saving this activity.']);
            }
            $occurred = CarbonImmutable::createFromFormat('!Y-m-d\TH:i', $data['occurred_local'], $timezone);
            if ($occurred->format('Y-m-d\TH:i') !== $data['occurred_local'] || $occurred->isFuture()) {
                throw ValidationException::withMessages(['occurred_local' => 'Choose a real local time that is not in the future.']);
            }
            $record = $id ? ActivityLog::forUser($user)->lockForUpdate()->findOrFail($id) : ActivityLog::withTrashed()->forUser($user)->where('request_key', $data['request_key'])->first();
            $attributes = ['subject_type' => $data['subject_type'], 'subject_id' => (int) $data['subject_id'], 'entry' => $data['entry'], 'minutes' => $data['minutes'] ?? null, 'occurred_at' => $occurred->utc()];
            if ($record && ! $id) {
                $unchanged = ! $record->trashed() && $record->revision === 1 && ! $record->fill($attributes)->isDirty();
                if (! $unchanged) {
                    throw ValidationException::withMessages(['request_key' => 'This submission was already saved or changed. Reload to start a new entry.']);
                }

                return;
            }
            if ($record && ($record->revision !== (int) $data['revision'] || $record->request_key !== $data['request_key'])) {
                throw ValidationException::withMessages(['revision' => 'This activity changed elsewhere. Reload before editing it.']);
            }
            if ($record && ($record->subject_type !== $data['subject_type'] || $record->subject_id !== (int) $data['subject_id'])) {
                throw ValidationException::withMessages(['subject_id' => 'An entry stays with its original subject. Remove it and log a new entry to change the subject.']);
            }
            if (! $record && (int) $data['revision'] !== 0) {
                throw ValidationException::withMessages(['revision' => 'Reload before adding this entry.']);
            }
            $class = $data['subject_type'] === 'project' ? Project::class : Domain::class;
            $subject = $class::forUser($user)->lockForUpdate()->findOrFail($data['subject_id']);
            $domainId = $record?->domain_id ?? ($subject instanceof Domain ? $subject->id : $subject->domain_id);
            Domain::forUser($user)->lockForUpdate()->findOrFail($domainId);
            $record ??= new ActivityLog(['user_id' => $user->id, 'domain_id' => $domainId, 'request_key' => $data['request_key'], 'revision' => 0, 'source' => 'manual']);
            $record->fill($attributes);
            $record->revision++;
            $record->save();
            $this->touch($user, 'domain', $domainId, 'activity', $record->id, $occurred->utc());
            if ($subject instanceof Project) {
                $this->touch($user, 'project', $subject->id, 'activity', $record->id, $occurred->utc());
            }
        });
    }

    /** Capture execution owns the transaction and owner lock. */
    public function capture(User $user, Domain|Project $subject, string $entry, ?int $minutes, CarbonImmutable $occurred): ActivityLog
    {
        $type = $subject instanceof Project ? 'project' : 'domain';
        $domainId = $subject instanceof Project ? $subject->domain_id : $subject->id;
        $record = ActivityLog::create(['user_id' => $user->id, 'subject_type' => $type, 'subject_id' => $subject->id, 'domain_id' => $domainId,
            'entry' => $entry, 'minutes' => $minutes, 'occurred_at' => $occurred, 'source' => 'capture', 'request_key' => Str::uuid()->toString()]);
        $this->touch($user, 'domain', $domainId, 'activity', $record->id, $occurred);
        if ($type === 'project') {
            $this->touch($user, 'project', $subject->id, 'activity', $record->id, $occurred);
        }

        return $record->fresh();
    }

    public function delete(User $user, int $id, int $revision): void
    {
        DB::transaction(function () use ($user, $id, $revision) {
            User::whereKey($user->id)->lockForUpdate()->firstOrFail();
            $record = ActivityLog::withTrashed()->forUser($user)->lockForUpdate()->findOrFail($id);
            if ($record->trashed()) {
                return;
            }
            if ($record->revision !== $revision) {
                throw ValidationException::withMessages(['revision' => 'This activity changed elsewhere. Reload before removing it.']);
            }
            $record->delete();
            $touches = DB::table('work_touches')->where('user_id', $user->id)->where('origin_type', 'activity')->where('origin_id', $id);
            $subjects = (clone $touches)->get();
            $touches->delete();
            foreach ($subjects as $subject) {
                $this->refresh($user, $subject->subject_type, $subject->subject_id);
            }
        });
    }

    /** Caller holds the owner lock inside the completion transaction. Reopening retains the historical touch. */
    public function completed(User $user, Task $task): void
    {
        $this->touch($user, 'domain', $task->domain_id, 'completion', $task->id, $task->completed_at);
        if ($task->project_id) {
            $this->touch($user, 'project', $task->project_id, 'completion', $task->id, $task->completed_at);
        }
    }

    public function restoreCompletionTouches(User $user, int $taskId, array $previous): void
    {
        $query = DB::table('work_touches')->where('user_id', $user->id)->where('origin_type', 'completion')->where('origin_id', $taskId);
        $subjects = $query->get()->map(fn ($row) => (array) $row)->merge($previous);
        $query->delete();
        foreach ($previous as $touch) {
            unset($touch['id']);
            DB::table('work_touches')->insert($touch);
        }
        foreach ($subjects as $subject) {
            $this->refresh($user, $subject['subject_type'], $subject['subject_id']);
        }
    }

    private function touch(User $user, string $type, int $id, string $origin, int $originId, $occurred): void
    {
        DB::table('work_touches')->updateOrInsert(['user_id' => $user->id, 'subject_type' => $type, 'subject_id' => $id, 'origin_type' => $origin, 'origin_id' => $originId], ['occurred_at' => $occurred]);
        $this->refresh($user, $type, $id);
    }

    private function refresh(User $user, string $type, int $id): void
    {
        $class = $type === 'project' ? Project::class : Domain::class;
        $latest = DB::table('work_touches')->where('user_id', $user->id)->where('subject_type', $type)->where('subject_id', $id)->max('occurred_at');
        $class::withTrashed()->forUser($user)->whereKey($id)->update(['last_touched_at' => $latest]);
    }
}
