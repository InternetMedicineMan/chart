<?php

namespace App\Models;

use App\Models\Concerns\OwnedByUser;
use Illuminate\Database\Eloquent\Model;

class ActionLog extends Model
{
    use OwnedByUser;

    protected $casts = ['payload' => 'array', 'after_snapshot' => 'array', 'executed_at' => 'datetime', 'undone_at' => 'datetime'];
}
