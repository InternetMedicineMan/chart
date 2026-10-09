<?php

namespace App\Models;

use App\Models\Concerns\OwnedByUser;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ConnectedCalendar extends Model
{
    use HasFactory, OwnedByUser;

    protected $hidden = ['sync_token'];

    protected $casts = ['is_primary' => 'boolean', 'last_synced_at' => 'immutable_datetime', 'last_attempt_at' => 'immutable_datetime', 'revision' => 'integer'];

    public function connection(): BelongsTo
    {
        return $this->belongsTo(CalendarConnection::class, 'calendar_connection_id');
    }
}
