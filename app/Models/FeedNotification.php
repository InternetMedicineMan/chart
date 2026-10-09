<?php

namespace App\Models;

use App\Models\Concerns\OwnedByUser;
use Database\Factories\FeedNotificationFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FeedNotification extends Model
{
    /** @use HasFactory<FeedNotificationFactory> */
    use HasFactory;

    use OwnedByUser;

    protected $table = 'notifications_feed';

    protected $casts = ['undo_payload' => 'array', 'undone_at' => 'datetime'];

    public function actionLog(): BelongsTo
    {
        return $this->belongsTo(ActionLog::class);
    }
}
