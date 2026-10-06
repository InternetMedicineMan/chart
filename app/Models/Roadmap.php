<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Roadmap extends Model
{
    /** @use HasFactory<\Database\Factories\RoadmapFactory> */
    use HasFactory;

    protected $fillable = [
        'user_id',
        'title',
        'description',
        'status',
        'votes_count',
    ];

    protected $casts = [
        'votes_count' => 'integer',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function votes(): HasMany
    {
        return $this->hasMany(RoadmapVote::class);
    }

    public function hasVotedBy(User $user): bool
    {
        return $this->votes()->where('user_id', $user->id)->exists();
    }
}
