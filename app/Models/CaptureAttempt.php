<?php

namespace App\Models;

use App\Models\Concerns\OwnedByUser;
use Illuminate\Database\Eloquent\Model;

class CaptureAttempt extends Model
{
    use OwnedByUser;
}
