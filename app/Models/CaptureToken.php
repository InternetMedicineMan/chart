<?php

namespace App\Models;

use App\Models\Concerns\OwnedByUser;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CaptureToken extends Model
{
    use HasFactory, OwnedByUser;

    protected $hidden = ['token_hash'];

    protected $casts = ['scopes' => 'array', 'rate_limit_per_hour' => 'integer', 'last_used_at' => 'datetime', 'revoked_at' => 'datetime'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
