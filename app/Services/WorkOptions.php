<?php

namespace App\Services;

use App\Models\Domain;
use App\Models\Person;
use App\Models\Project;
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
            'people' => Person::forUser($user)->orderBy('name')->get(['id', 'name', 'company']),
            'timezone' => $timezone,
            'activityRequestKey' => Str::uuid()->toString(),
            'localNow' => now()->setTimezone($timezone)->format('Y-m-d\TH:i'),
            'today' => app(LocalDate::class)->today($user),
        ];
    }
}
