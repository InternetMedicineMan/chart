<?php

namespace App\Models;

use App\Models\Concerns\OwnedByUser;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class ActivityLog extends Model
{
    use HasFactory, OwnedByUser, SoftDeletes;

    protected $casts = ['occurred_at' => 'immutable_datetime', 'minutes' => 'integer', 'revision' => 'integer'];
}
