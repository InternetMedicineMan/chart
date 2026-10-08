<?php

namespace App\Models;

use App\Models\Concerns\OwnedByUser;
use Illuminate\Database\Eloquent\Model;

class CaptureItem extends Model
{
    use OwnedByUser;

    protected $casts = ['payload' => 'array', 'candidates' => 'array', 'confidence' => 'float', 'executed_at' => 'datetime', 'undone_at' => 'datetime'];
}
