<?php
namespace App\Patterns\Adapter;

use App\Enums\Office;
use App\Enums\Stage;

// ADAPTER PATTERN - the "target" interface.
// The stage rules only ever talk to this interface, never to the database directly.
// The two adapters (EloquentSponsorAdapter and ArraySponsorData) each translate
// their own kind of data into these simple yes/no questions.

/**
 * The ONE shape of sponsor information that the pattern classes understand.
 * Anything that can answer these questions can be checked, whether it comes
 * from the database or from a plain array in a test.
 */
interface SponsorData
{
    // Which stage the sponsor is in now.
    public function stage(): Stage;
    // Has the deal been marked as finalized?
    public function dealFinalized(): bool;
    // Has an amount above zero been agreed?
    public function hasAgreedAmount(): bool;
    // Has at least one representative been added?
    public function hasRepresentative(): bool;
    // Does their booth need electricity?
    public function requiresElectricity(): bool;
    // Has the booth setup been arranged?
    public function setupArranged(): bool;
    /** 'pending', 'submitted' or 'approved' */
    public function approvalStatus(Office $office): string;
    // Have they arrived on campus on at least one event day?
    public function arrived(): bool;
    // Have they left the campus on every event day?
    public function left(): bool;
}
