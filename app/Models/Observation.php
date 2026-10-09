<?php

namespace App\Models;

use App\Models\Concerns\OwnedByUser;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Observation extends Model
{
    use HasFactory, OwnedByUser;

    protected $casts = ['data' => 'array', 'score' => 'integer', 'roll_up_count' => 'integer', 'observed_on' => 'date:Y-m-d', 'resolved_at' => 'immutable_datetime', 'dismissed_at' => 'immutable_datetime', 'snoozed_until' => 'immutable_datetime', 'acted_on_at' => 'immutable_datetime', 'expires_at' => 'immutable_datetime'];

    public function scopeVisible(Builder $query): Builder
    {
        return $query->whereNull('resolved_at')->whereNull('dismissed_at')->where('expires_at', '>', now())->where(fn ($q) => $q->whereNull('snoozed_until')->orWhere('snoozed_until', '<=', now()));
    }
}
