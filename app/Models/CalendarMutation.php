<?php

namespace App\Models;

use App\Models\Concerns\OwnedByUser;
use Illuminate\Database\Eloquent\Model;

class CalendarMutation extends Model
{
    use OwnedByUser;

    protected $attributes = ['status' => 'pending', 'attempts' => 0];

    protected $casts = ['payload' => 'array', 'before_payload' => 'array', 'available_at' => 'immutable_datetime', 'applied_at' => 'immutable_datetime', 'attempts' => 'integer'];
}
