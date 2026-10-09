<?php

namespace App\Models;

use App\Models\Concerns\OwnedByUser;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CalendarEvent extends Model
{
    use HasFactory, OwnedByUser;

    protected $hidden = ['remote_payload'];

    protected $casts = ['remote_payload' => 'array', 'starts_at' => 'immutable_datetime', 'ends_at' => 'immutable_datetime', 'start_date' => 'date:Y-m-d', 'end_date' => 'date:Y-m-d', 'all_day' => 'boolean', 'is_series' => 'boolean', 'synced_at' => 'immutable_datetime', 'removed_at' => 'immutable_datetime', 'revision' => 'integer'];

    public function calendar(): BelongsTo
    {
        return $this->belongsTo(ConnectedCalendar::class, 'connected_calendar_id');
    }

    public function scopeVisible(Builder $query, User $user): Builder
    {
        return $query->forUser($user)->where('is_series', false)->whereNull('removed_at')->where('status', '!=', 'cancelled')
            ->whereHas('calendar', fn ($q) => $q->forUser($user)->where('mode', '!=', 'off')->whereHas('connection', fn ($q) => $q->forUser($user)->where('status', '!=', 'disconnected')));
    }
}
