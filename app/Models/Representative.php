<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

// A person a sponsor sends to campus.
class Representative extends Model
{
    protected $fillable = [
        'sponsor_id',
        'name',
        'phone',
    ];

    public function sponsor(): BelongsTo
    {
        return $this->belongsTo(Sponsor::class);
    }
}
