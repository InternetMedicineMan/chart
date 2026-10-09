<?php

namespace App\Models;

use App\Enums\TaskSource;
use App\Models\Concerns\OwnedByUser;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Task extends Model
{
    use OwnedByUser, SoftDeletes;

    protected $casts = ['source' => TaskSource::class, 'due_date' => 'date:Y-m-d', 'completed_at' => 'datetime', 'needs_review' => 'boolean', 'priority' => 'integer', 'waiting_since' => 'datetime', 'wait_expected_by' => 'date:Y-m-d', 'wait_revision' => 'integer'];

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
