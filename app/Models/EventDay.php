<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

// One day a sponsor is on campus.
class EventDay extends Model
{
    protected $fillable = [
        'sponsor_id',
        'event_date',
        'location',
        'entry_pass',
        'exit_pass',
        'arrived_at',
        'left_at',
    ];

    protected function casts(): array
    {
        return [
            'event_date' => 'date',
            'entry_pass' => 'boolean',
            'exit_pass' => 'boolean',
            'arrived_at' => 'datetime',
            'left_at' => 'datetime',
        ];
    }

    public function sponsor(): BelongsTo
    {
        return $this->belongsTo(Sponsor::class);
    }
}
