<?php

namespace App\Patterns\Adapter;

use App\Enums\Office;
use App\Enums\Stage;
use App\Models\Sponsor;

// ADAPTER PATTERN - adapter for the database.
// It wraps a Sponsor model and answers the SponsorData questions by reading
// the model's columns and related rows (approvals, representatives, event days).
// Load those relations first with Sponsor::with([...]) to avoid extra queries.

/** Adapter 1: makes a database Sponsor model look like SponsorData. */
final class EloquentSponsorAdapter implements SponsorData
{
    // Keep the Sponsor model we are adapting.
    public function __construct(private Sponsor $sponsor) {}

    public function stage(): Stage
    {
        // Already a Stage enum, because the model casts the column.
        return $this->sponsor->stage;
    }

    public function dealFinalized(): bool
    {
        return $this->sponsor->deal_status === 'finalized';
    }

    public function hasAgreedAmount(): bool
    {
        // An empty amount (null) becomes 0, which counts as "no amount".
        return (float) $this->sponsor->agreed_amount > 0;
    }

    public function hasRepresentative(): bool
    {
        return $this->sponsor->representatives->isNotEmpty();
    }

    public function requiresElectricity(): bool
    {
        return (bool) $this->sponsor->requires_electricity;
    }

    public function setupArranged(): bool
    {
        return (bool) $this->sponsor->setup_arranged;
    }

    public function approvalStatus(Office $office): string
    {
        // Find this office's approval row for the sponsor.
        $approval = $this->sponsor->approvals->firstWhere('office', $office->value);

        // No row yet means nothing has happened, so treat it as pending.
        return $approval?->status ?? 'pending';
    }

    public function arrived(): bool
    {
        // True if any event day has an arrival time.
        return $this->sponsor->eventDays->contains(fn ($day) => $day->arrived_at !== null);
    }

    public function left(): bool
    {
        $days = $this->sponsor->eventDays;

        // There must be event days, and every one of them needs a leaving time.
        return $days->isNotEmpty() && $days->every(fn ($day) => $day->left_at !== null);
    }
}
