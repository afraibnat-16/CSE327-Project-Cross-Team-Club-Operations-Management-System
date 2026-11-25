<?php
namespace App\Patterns\Factory;

use App\Enums\Office;
use App\Patterns\Adapter\SponsorData;

// FACTORY METHOD PATTERN - the "product".
// Every kind of approval (Student Affairs, Ops & Management, Gate pass) is
// a child of this class. "abstract" means you cannot create it directly;
// each child must fill in office() and canSubmit() for its own office.

/** The "product": what every kind of approval can do. */
abstract class ApprovalRequest
{
    // Which office this approval belongs to.
    abstract public function office(): Office;

    /** Can this approval be logged as submitted yet? */
    abstract public function canSubmit(SponsorData $sponsor): bool;

    // Shared by all children: the office's display name, e.g. "Student Affairs".
    public function label(): string
    {
        return $this->office()->label();
    }

    // Shared by all children: a fresh approval starts as pending, not yet submitted.
    /** The values for a new row in the approvals table. */
    public function newRow(): array
    {
        return ['office' => $this->office()->value, 'status' => 'pending', 'submitted_on' => null];
    }
}
