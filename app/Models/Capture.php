<?php

namespace App\Models;

use App\Models\Concerns\OwnedByUser;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Capture extends Model
{
    use OwnedByUser;

    protected $casts = ['parsed' => 'array', 'fallback_snapshot' => 'array', 'client_captured_at' => 'immutable_datetime', 'available_at' => 'datetime', 'lease_until' => 'datetime', 'attempts' => 'integer'];

    protected $hidden = ['lease', 'fallback_snapshot'];

    public function items(): HasMany
    {
        return $this->hasMany(CaptureItem::class);
    }
}
