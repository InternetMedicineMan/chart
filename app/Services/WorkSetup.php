<?php

namespace App\Services;

use App\Models\AppSetting;
use App\Models\Domain;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class WorkSetup
{
    public function inbox(User $user): Domain
    {
        return Domain::forUser($user)->firstOrCreate(['slug' => 'inbox'], [
            'user_id' => $user->id, 'name' => 'Inbox', 'is_inbox' => true, 'quiet_enabled' => false,
        ]);
    }

    public function initialize(User $user): void
    {
        DB::transaction(function () use ($user) {
            $this->inbox($user);
            foreach ([
                ['Wondercide', 'work', 7], ['Turbowebs & Clients', 'work', 7],
                ['Writing', 'personal', 8], ['Ministry & Church', 'personal', 14],
                ['Family', 'personal', 14], ['Personal / Home', 'personal', 14],
            ] as $order => [$name, $sphere, $cadence]) {
                // Retain edits and soft-deleted domains when setup is run again.
                Domain::withTrashed()->forUser($user)->firstOrCreate(['slug' => Str::slug($name)], [
                    'user_id' => $user->id, 'name' => $name, 'sphere' => $sphere,
                    'cadence_days' => $cadence, 'sort_order' => $order + 1,
                ]);
            }
            AppSetting::forUser($user)->firstOrCreate(['key' => 'timezone'], ['user_id' => $user->id, 'value' => 'America/Chicago']);
        });
    }
}
