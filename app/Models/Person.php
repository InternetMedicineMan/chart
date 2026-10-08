<?php

namespace App\Models;

use App\Enums\PersonRelationship;
use App\Models\Concerns\OwnedByUser;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Person extends Model
{
    use OwnedByUser, SoftDeletes;

    protected $casts = ['relationship' => PersonRelationship::class];
}
