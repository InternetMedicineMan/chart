<?php

namespace App\Services;

use App\Models\Domain;
use App\Models\Milestone;
use App\Models\Person;
use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use Illuminate\Support\Str;

class WorkOptions
{
    public function forUser(User $user): array
    {
        $timezone = app(LocalDate::class)->timezone($user);

        return [
            'domains' => Domain::forUser($user)->orderByDesc('is_inbox')->orderBy('sort_order')->orderBy('name')->get(),
            'projects' => Project::forUser($user)->orderBy('name')->get(['id', 'name', 'domain_id', 'lifecycle', 'wait_revision', 'holder_person_id', 'wait_expected_by']),
            'milestones' => Milestone::forUser($user)->orderBy('sort_order')->orderBy('id')->get(['id', 'project_id', 'title', 'completed_at', 'revision']),
            'parentTasks' => Task::forUser($user)->whereNull('parent_task_id')->whereNull('completed_at')->orderBy('title')->get(['id', 'title', 'project_id', 'domain_id', 'milestone_id', 'revision']),
            'people' => Person::forUser($user)->orderBy('name')->get(['id', 'name', 'company']),
            'timezone' => $timezone,
            'activityRequestKey' => Str::uuid()->toString(),
            'localNow' => now()->setTimezone($timezone)->format('Y-m-d\TH:i'),
            'today' => app(LocalDate::class)->today($user),
        ];
    }
}
