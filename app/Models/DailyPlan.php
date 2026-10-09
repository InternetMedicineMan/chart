<?php

namespace App\Models;

use App\Models\Concerns\OwnedByUser;
use Illuminate\Database\Eloquent\Model;

class DailyPlan extends Model
{
    use OwnedByUser;

    protected $casts = ['plan_date' => 'date:Y-m-d', 'top_task_ids' => 'array', 'revision' => 'integer'];
}
