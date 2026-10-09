<?php

namespace App\Models;

use App\Models\Concerns\OwnedByUser;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Milestone extends Model
{
    use OwnedByUser, SoftDeletes;

    protected $casts = ['weight' => 'integer', 'sort_order' => 'integer', 'revision' => 'integer', 'due_date' => 'date:Y-m-d', 'completed_at' => 'datetime'];

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }
}
