<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

// A task about a sponsor given to one member. status: open or done
class Assignment extends Model
{
    protected $fillable = [
        'sponsor_id',
        'user_id',
        'task',
        'status',
        'due_on',
    ];

    protected function casts(): array
    {
        return [
            'due_on' => 'date',
        ];
    }

    public function sponsor(): BelongsTo
    {
        return $this->belongsTo(Sponsor::class);
    }

    // The member who must do the task.
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
