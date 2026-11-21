<?php

namespace App\Models;

use App\Enums\Stage;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

// An organization that sponsors a CAP Force event.
class Sponsor extends Model
{
    // Columns that may be filled in one go, e.g. Sponsor::create([...]).
    protected $fillable = [
        'organization_name',
        'type',
        'contact_name',
        'contact_phone',
        'contact_email',
        'agreed_amount',
        'items',
        'setup_needs',
        'requires_electricity',
        'setup_arranged',
        'stage',
        'deal_status',
        'claimed_by',
    ];

    // Default values for a new sponsor, before it is saved.
    protected $attributes = [
        'stage' => 'negotiation',
        'deal_status' => 'negotiating',
        'requires_electricity' => false,
        'setup_arranged' => false,
    ];

    // Turn database values into proper PHP types when we read them.
    protected function casts(): array
    {
        return [
            'stage' => Stage::class,          // "approval" becomes Stage::Approval
            'agreed_amount' => 'decimal:2',
            'requires_electricity' => 'boolean',
            'setup_arranged' => 'boolean',
        ];
    }

    // The member who is handling this sponsor.
    public function claimer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'claimed_by');
    }

    public function representatives(): HasMany
    {
        return $this->hasMany(Representative::class);
    }

    public function approvals(): HasMany
    {
        return $this->hasMany(Approval::class);
    }

    public function assignments(): HasMany
    {
        return $this->hasMany(Assignment::class);
    }

    public function notes(): HasMany
    {
        return $this->hasMany(Note::class);
    }

    public function eventDays(): HasMany
    {
        return $this->hasMany(EventDay::class);
    }

    public function activityLogs(): HasMany
    {
        return $this->hasMany(ActivityLog::class);
    }
}
