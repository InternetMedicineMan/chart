<?php

namespace App\Models;

use App\Models\Concerns\OwnedByUser;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasOne;

class PushSubscription extends Model
{
    use HasFactory, OwnedByUser;

    protected $casts = ['subscription' => 'encrypted:array'];

    protected $hidden = ['subscription', 'endpoint_hash'];

    public function latestDelivery(): HasOne
    {
        return $this->hasOne(PushDelivery::class)->latestOfMany();
    }
}
