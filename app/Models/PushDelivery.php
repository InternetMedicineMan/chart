<?php

namespace App\Models;

use App\Models\Concerns\OwnedByUser;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PushDelivery extends Model
{
    use HasFactory, OwnedByUser;

    protected $casts = ['available_at' => 'immutable_datetime', 'attempts' => 'integer'];
}
