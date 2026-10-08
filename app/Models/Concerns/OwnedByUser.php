<?php

namespace App\Models\Concerns;

use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

trait OwnedByUser
{
    public function scopeForUser(Builder $query, User|int $user): Builder
    {
        return $query->where($this->qualifyColumn('user_id'), $user instanceof User ? $user->id : $user);
    }
}
