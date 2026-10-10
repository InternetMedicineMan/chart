<?php

namespace App\Models;

use App\Models\Concerns\OwnedByUser;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Reminder extends Model
{
    use HasFactory, OwnedByUser;

    protected $casts = ['scheduled_at' => 'immutable_datetime'];
}
