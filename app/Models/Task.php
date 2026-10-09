<?php

namespace App\Models;

use App\Enums\TaskSource;
use App\Models\Concerns\OwnedByUser;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Task extends Model
{
    use OwnedByUser, SoftDeletes;

    protected $casts = ['revision' => 'integer', 'recurrence_anchor' => 'date:Y-m-d', 'recurrence_index' => 'integer', 'source' => TaskSource::class, 'due_date' => 'date:Y-m-d', 'completed_at' => 'datetime', 'needs_review' => 'boolean', 'priority' => 'integer', 'waiting_since' => 'datetime', 'wait_expected_by' => 'date:Y-m-d', 'wait_revision' => 'integer'];

    protected static function booted(): void
    {
        static::updating(function (Task $task) {
            if ($task->isDirty()) {
                $task->revision = (int) $task->getOriginal('revision') + 1;
            }
        });
    }

    public function subtasks(): HasMany
    {
        return $this->hasMany(Task::class, 'parent_task_id');
    }

    public function parentTask(): BelongsTo
    {
        return $this->belongsTo(Task::class, 'parent_task_id');
    }

    public function milestone(): BelongsTo
    {
        return $this->belongsTo(Milestone::class);
    }

    public function waitingPerson(): BelongsTo
    {
        return $this->belongsTo(Person::class, 'waiting_on_person_id');
    }

    public function domain(): BelongsTo
    {
        return $this->belongsTo(Domain::class);
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }
}
