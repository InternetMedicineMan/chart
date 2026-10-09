<?php

namespace App\Services;

use App\Models\Capture;
use App\Models\Domain;
use App\Models\Person;
use App\Models\Project;
use App\Models\User;

class CaptureContext
{
    public function forCapture(Capture $capture): array
    {
        return [
            'timezone' => $capture->timezone,
            'client_captured_at' => $capture->client_captured_at->setTimezone($capture->timezone)->toIso8601String(),
            'current_local_time' => now()->setTimezone($capture->timezone)->toIso8601String(),
            'source' => $capture->source, 'mode' => $capture->mode,
            'domains' => Domain::forUser($capture->user_id)->whereNull('archived_at')->get(['name', 'sphere'])->toArray(),
            'projects' => Project::forUser($capture->user_id)->where('lifecycle', 'active')->with(['domain:id,name', 'holderPerson' => fn ($query) => $query->forUser($capture->user_id)->select(['id', 'name'])])->get(['id', 'name', 'domain_id', 'holder', 'holder_person_id'])->toArray(),
            'tasks' => app(DailyPlanning::class)->activeTasks(User::findOrFail($capture->user_id))->whereNull('completed_at')->with(['project:id,name', 'waitingPerson' => fn ($query) => $query->forUser($capture->user_id)->select(['id', 'name'])])->orderByDesc('id')->limit(200)->get(['id', 'title', 'project_id', 'due_date', 'recurrence_rule', 'waiting_on_person_id'])->toArray(),
            'people' => Person::forUser($capture->user_id)->get(['name', 'company'])->toArray(),
        ];
    }
}
