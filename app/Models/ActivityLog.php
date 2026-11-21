<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

// One line in the activity history: who did what, and to which sponsor.
class ActivityLog extends Model
{
    protected $fillable = [
        'sponsor_id',
        'user_id',
        'action',
        'details',
    ];

    // Can be empty if the log is not about one sponsor.
    public function sponsor(): BelongsTo
    {
        return $this->belongsTo(Sponsor::class);
    }

    // Can be empty if the system did it, or the member was deleted.
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
