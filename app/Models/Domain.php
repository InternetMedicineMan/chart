<?php

namespace App\Models;

use App\Enums\Sphere;
use App\Models\Concerns\OwnedByUser;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Validation\ValidationException;

class Domain extends Model
{
    use OwnedByUser, SoftDeletes;

    protected $casts = ['sphere' => Sphere::class, 'is_inbox' => 'boolean', 'parked' => 'boolean', 'quiet_enabled' => 'boolean', 'last_touched_at' => 'datetime', 'last_shipped_at' => 'datetime', 'archived_at' => 'datetime'];

    protected static function booted(): void
    {
        static::deleting(function (Domain $domain) {
            if (ActivityLog::forUser($domain->user_id)->where('domain_id', $domain->id)->exists() || $domain->is_inbox || $domain->tasks()->withTrashed()->exists() || $domain->projects()->withTrashed()->exists()) {
                throw ValidationException::withMessages(['domain' => 'The Inbox and domains containing work or activity must be kept. Move the work first.']);
            }
        });
    }

    public function tasks(): HasMany
    {
        return $this->hasMany(Task::class);
    }

    public function projects(): HasMany
    {
        return $this->hasMany(Project::class);
    }
}
