<?php

namespace App\Services;

use App\Models\Domain;
use App\Models\Person;
use App\Models\Project;
use App\Models\User;

class WorkOptions
{
    public function forUser(User $user): array
    {
        return [
            'domains' => Domain::forUser($user)->orderByDesc('is_inbox')->orderBy('sort_order')->orderBy('name')->get(),
            'projects' => Project::forUser($user)->orderBy('name')->get(['id', 'name', 'domain_id', 'lifecycle']),
            'people' => Person::forUser($user)->orderBy('name')->get(['id', 'name', 'company']),
            'timezone' => app(LocalDate::class)->timezone($user),
            'today' => app(LocalDate::class)->today($user),
        ];
    }
}
