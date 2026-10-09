<?php

namespace App\Models;

use App\Models\Concerns\OwnedByUser;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CalendarConnection extends Model
{
    use HasFactory, OwnedByUser;

    protected $hidden = ['access_token', 'refresh_token'];

    protected $casts = ['access_token' => 'encrypted', 'refresh_token' => 'encrypted', 'scopes' => 'array', 'expires_at' => 'immutable_datetime', 'revision' => 'integer'];
}
