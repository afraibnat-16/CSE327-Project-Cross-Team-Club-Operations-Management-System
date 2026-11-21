<?php

namespace App\Enums;

// The stages a sponsor moves through, in order.
// The string on the right is what gets saved in the database.
enum Stage: string
{
    case Negotiation = 'negotiation';
    case Approval = 'approval';
    case Logistics = 'logistics';
    case Ready = 'ready';
    case OnCampus = 'on_campus';
    case Completed = 'completed';
    case Cancelled = 'cancelled';

    // A human-friendly name to show on screen, e.g. "On campus".
    public function label(): string
    {
        return match ($this) {
            self::OnCampus => 'On campus',
            default => ucfirst($this->value),
        };
    }
}
