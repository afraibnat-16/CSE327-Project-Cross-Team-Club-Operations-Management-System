<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

// A comment a member writes about a sponsor.
class Note extends Model
{
    protected $fillable = [
        'sponsor_id',
        'user_id',
        'body',
        'follow_up_on',
    ];

    protected function casts(): array
    {
        return [
            'follow_up_on' => 'date',
        ];
    }

    public function sponsor(): BelongsTo
    {
        return $this->belongsTo(Sponsor::class);
    }

    // The member who wrote the note.
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
