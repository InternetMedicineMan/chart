<?php

namespace App\Models;

use App\Enums\NoteKind;
use App\Models\Concerns\OwnedByUser;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Note extends Model
{
    use OwnedByUser, SoftDeletes;

    protected $casts = ['kind' => NoteKind::class, 'needs_review' => 'boolean', 'reviewed_at' => 'datetime'];
}
