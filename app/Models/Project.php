<?php

namespace App\Models;

use App\Enums\ProjectLifecycle;
use App\Enums\ProjectType;
use App\Enums\WorkHolder;
use App\Models\Concerns\OwnedByUser;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Project extends Model
{
    use OwnedByUser, SoftDeletes;

    protected $casts = ['holder' => WorkHolder::class, 'type' => ProjectType::class, 'lifecycle' => ProjectLifecycle::class, 'target_date' => 'date:Y-m-d', 'quiet_enabled' => 'boolean', 'holder_since' => 'datetime', 'last_touched_at' => 'datetime', 'reviewed_at' => 'datetime', 'completed_at' => 'datetime'];

    public function domain(): BelongsTo
    {
        return $this->belongsTo(Domain::class);
    }

    public function tasks(): HasMany
    {
        return $this->hasMany(Task::class);
    }
}
