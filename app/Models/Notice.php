<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

// A message left for a team, e.g. telling Admin that a sponsor reached Approval.
class Notice extends Model
{
    protected $fillable = [
        'team',
        'sponsor_id',
        'message',
        'read_at',
    ];

    protected function casts(): array
    {
        return [
            'read_at' => 'datetime',   // empty (null) means not read yet
        ];
    }

    // The sponsor this notice is about (can be empty).
    public function sponsor(): BelongsTo
    {
        return $this->belongsTo(Sponsor::class);
    }
}
