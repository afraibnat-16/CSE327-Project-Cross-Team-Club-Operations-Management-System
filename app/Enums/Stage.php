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

    // The name shown on screens.
    public function label(): string
    {
        return match ($this) {
            self::OnCampus => 'On campus',
            default => ucfirst($this->value),
        };
    }

    // The team that has to act while a sponsor is in this stage.
    // Completed and Cancelled are finished, so nobody owns them (null).
    public function owner(): ?Team
    {
        return match ($this) {
            self::Negotiation => Team::Corporate,
            self::Approval => Team::Admin,
            self::Logistics, self::Ready, self::OnCampus => Team::Provision,
            self::Completed, self::Cancelled => null,
        };
    }
}
