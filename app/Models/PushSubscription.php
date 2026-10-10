<?php

namespace App\Models;

use App\Models\Concerns\OwnedByUser;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PushSubscription extends Model
{
    use HasFactory, OwnedByUser;

    protected $casts = ['subscription' => 'encrypted:array'];

    protected $hidden = ['subscription', 'endpoint_hash'];
}
