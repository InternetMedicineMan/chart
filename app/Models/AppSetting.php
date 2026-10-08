<?php

namespace App\Models;

use App\Models\Concerns\OwnedByUser;
use Illuminate\Database\Eloquent\Model;

class AppSetting extends Model
{
    use OwnedByUser;

    protected $casts = ['value' => 'json'];
}
